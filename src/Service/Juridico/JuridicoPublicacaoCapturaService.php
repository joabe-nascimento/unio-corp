<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoPublicacao;
use App\Entity\JuridicoPublicacaoCaptura;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoPublicacaoCapturaRepository;
use App\Repository\JuridicoPublicacaoConfigRepository;
use App\Repository\JuridicoPublicacaoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Sincroniza publicações do DJEN para o escritório.
 */
final class JuridicoPublicacaoCapturaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoPublicacaoCapturaRepository $capturaRepo,
        private JuridicoPublicacaoRepository $publicacaoRepo,
        private JuridicoPublicacaoConfigRepository $configRepo,
        private DjenApiClient $djen,
        private JuridicoPublicacaoMatchingService $matching,
        private JuridicoPublicacaoTriagemService $triagem,
        private JuridicoPrazoService $prazoService,
        private JuridicoPublicacaoAlertaService $alerta,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{novas: int, atualizadas: int, triadas: int, prazos: int, erros: int}
     */
    public function capturarEmpresa(Empresa $empresa, int $diasJanela = 3, bool $triarNovas = true): array
    {
        $stats = ['novas' => 0, 'atualizadas' => 0, 'triadas' => 0, 'prazos' => 0, 'erros' => 0];
        $capturas = $this->capturaRepo->findAtivasByEmpresa($empresa);
        /** @var list<JuridicoPublicacao> $novasPubs */
        $novasPubs = [];

        if ($capturas === []) {
            return $stats;
        }

        $fim = new \DateTimeImmutable('today');
        $inicio = $fim->modify('-' . max(1, $diasJanela) . ' days');

        foreach ($capturas as $captura) {
            try {
                $items = $this->djen->buscarPorOab($captura->getNumeroOab(), $captura->getUfOab(), $inicio, $fim);
                foreach ($items as $item) {
                    $result = $this->importarItem($empresa, $item, $triarNovas);
                    if ($result['status'] === 'triadas') {
                        ++$stats['novas'];
                        ++$stats['triadas'];
                        $novasPubs[] = $result['publicacao'];
                        if ($this->tentarPrazoAutomatico($result['publicacao'])) {
                            ++$stats['prazos'];
                        }
                    } elseif ($result['status'] === 'novas') {
                        ++$stats['novas'];
                        $novasPubs[] = $result['publicacao'];
                    } else {
                        ++$stats['atualizadas'];
                    }
                }
                $captura->setUltimaCapturaEm(new \DateTimeImmutable());
            } catch (\Throwable $e) {
                ++$stats['erros'];
                $this->logger->warning('Captura DJEN falhou para OAB {oab}: {msg}', [
                    'oab' => $captura->labelOab(),
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        $this->em->flush();

        if ($novasPubs !== []) {
            $this->alerta->notificarNovas($empresa, $novasPubs);
        }

        return $stats;
    }

    private function tentarPrazoAutomatico(JuridicoPublicacao $publicacao): bool
    {
        if ($publicacao->isPrazoCriado() || $publicacao->isCancelada()) {
            return false;
        }

        $config = $this->configRepo->getOrCreate($publicacao->getEmpresa());
        if (!$config->isPrazoAutomatico()) {
            return false;
        }

        $dias = $publicacao->getIaSugestaoPrazoDias();
        if ($dias === null || $dias < 1 || $publicacao->getProcesso() === null) {
            return false;
        }

        try {
            $this->prazoService->create($publicacao->getEmpresa(), [
                'tipo' => $publicacao->getIaSugestaoTipoPrazo() ?? 'Manifestação',
                'descricao' => 'Gerado automaticamente a partir de publicação DJEN'
                    . ($publicacao->getIaResumo() ? ': ' . mb_substr($publicacao->getIaResumo(), 0, 200) : ''),
                'data_limite' => (new \DateTimeImmutable('today'))->modify('+' . $dias . ' days')->format('Y-m-d'),
                'processo_id' => $publicacao->getProcesso()->getId(),
                'responsavel_id' => $publicacao->getProcesso()->getResponsavel()?->getId() ?? 0,
            ]);
            $publicacao->setPrazoCriado(true)->touch();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{novas: int, atualizadas: int, triadas: int, prazos: int, erros: int}
     */
    public function capturarTodas(int $diasJanela = 2, bool $triarNovas = false): array
    {
        $totais = ['novas' => 0, 'atualizadas' => 0, 'triadas' => 0, 'prazos' => 0, 'erros' => 0];
        $capturas = $this->capturaRepo->findTodasAtivas();
        $empresas = [];

        foreach ($capturas as $captura) {
            $empresas[$captura->getEmpresa()->getId()] = $captura->getEmpresa();
        }

        foreach ($empresas as $empresa) {
            $stats = $this->capturarEmpresa($empresa, $diasJanela, $triarNovas);
            foreach ($stats as $k => $v) {
                $totais[$k] += $v;
            }
        }

        return $totais;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array{status: 'novas'|'atualizadas'|'triadas', publicacao?: JuridicoPublicacao}
     */
    private function importarItem(Empresa $empresa, array $item, bool $triarNovas): array
    {
        $djenId = (int) ($item['id'] ?? 0);
        if ($djenId <= 0) {
            return ['status' => 'atualizadas'];
        }

        $existente = $this->publicacaoRepo->findByDjenId($empresa, $djenId);
        $cancelamento = trim((string) ($item['motivo_cancelamento'] ?? ''));

        if ($existente !== null) {
            if ($cancelamento !== '') {
                $existente
                    ->setMotivoCancelamento($cancelamento)
                    ->setStatus(JuridicoPublicacao::STATUS_CANCELADA)
                    ->touch();
            }

            return ['status' => 'atualizadas'];
        }

        $numeroRaw = (string) ($item['numeroprocessocommascara'] ?? $item['numero_processo'] ?? '');
        $numeroNorm = CnjNumeroNormalizer::apenasDigitos($numeroRaw);

        $pub = new JuridicoPublicacao();
        $pub->setEmpresa($empresa);
        $pub->setDjenId($djenId);
        $pub->setHash(isset($item['hash']) ? (string) $item['hash'] : null);
        $pub->setFonte(JuridicoPublicacao::FONTE_DJEN);
        $pub->setNumeroProcesso(CnjNumeroNormalizer::formatarMascara($numeroNorm) ?? $numeroRaw);
        $pub->setNumeroProcessoNorm($numeroNorm);
        $pub->setTipoComunicacao(isset($item['tipoComunicacao']) ? (string) $item['tipoComunicacao'] : null);
        $pub->setTipoDocumento(isset($item['tipoDocumento']) ? (string) $item['tipoDocumento'] : null);
        $pub->setTribunal(isset($item['siglaTribunal']) ? (string) $item['siglaTribunal'] : null);
        $pub->setOrgao(isset($item['nomeOrgao']) ? (string) $item['nomeOrgao'] : null);
        $pub->setClasse(isset($item['nomeClasse']) ? (string) $item['nomeClasse'] : null);
        $pub->setLink(isset($item['link']) ? (string) $item['link'] : null);
        $pub->setTexto(DjenApiClient::sanitizarHtml(isset($item['texto']) ? (string) $item['texto'] : null));

        $dataDisp = (string) ($item['data_disponibilizacao'] ?? '');
        if ($dataDisp !== '') {
            try {
                $pub->setDataDisponibilizacao(new \DateTimeImmutable($dataDisp));
            } catch (\Throwable) {
            }
        }

        if ($cancelamento !== '') {
            $pub->setMotivoCancelamento($cancelamento);
            $pub->setStatus(JuridicoPublicacao::STATUS_CANCELADA);
        }

        $this->matching->aplicar($pub);
        $this->em->persist($pub);

        if ($triarNovas && !$pub->isCancelada() && $pub->getTexto() !== null) {
            $resultado = $this->triagem->triar($pub);
            if ($resultado !== null) {
                $this->triagem->aplicarResultado($pub, $resultado);

                return ['status' => 'triadas', 'publicacao' => $pub];
            }
        }

        return ['status' => 'novas', 'publicacao' => $pub];
    }

    public function adicionarOab(Empresa $empresa, string $numeroOab, string $ufOab): JuridicoPublicacaoCaptura
    {
        $numeroOab = preg_replace('/\D+/', '', $numeroOab) ?? '';
        $ufOab = strtoupper(trim($ufOab));

        if ($numeroOab === '' || strlen($ufOab) !== 2) {
            throw new JuridicoProcessException('Informe número OAB e UF válidos.');
        }

        $existente = $this->capturaRepo->findOneBy([
            'empresa' => $empresa,
            'numeroOab' => $numeroOab,
            'ufOab' => $ufOab,
        ]);

        if ($existente !== null) {
            $existente->setAtivo(true);
            $this->em->flush();

            return $existente;
        }

        $captura = new JuridicoPublicacaoCaptura();
        $captura->setEmpresa($empresa);
        $captura->setNumeroOab($numeroOab);
        $captura->setUfOab($ufOab);
        $this->em->persist($captura);
        $this->em->flush();

        return $captura;
    }

    public function removerOab(JuridicoPublicacaoCaptura $captura): void
    {
        $captura->setAtivo(false);
        $this->em->flush();
    }
}
