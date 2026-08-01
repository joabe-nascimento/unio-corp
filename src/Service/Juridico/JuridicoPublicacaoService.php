<?php

namespace App\Service\Juridico;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\JuridicoPublicacao;
use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\JuridicoPublicacaoConfigRepository;
use App\Repository\JuridicoPublicacaoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JuridicoPublicacaoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoPublicacaoRepository $repo,
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoPublicacaoMatchingService $matching,
        private JuridicoPublicacaoTriagemService $triagem,
        private JuridicoPrazoService $prazoService,
        private JuridicoPublicacaoConfigRepository $configRepo,
        private DjenApiClient $djen,
    ) {
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoPublicacao
    {
        $pub = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$pub) {
            throw new JuridicoProcessException('Publicação não encontrada.');
        }

        return $pub;
    }

    /** @return list<JuridicoPublicacao> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $prioridade = null, ?string $q = null): array
    {
        return $this->repo->findForEmpresa($empresa, $status, $prioridade, $q);
    }

    /** @return array{nao_lidas: int, triagem_pendente: int} */
    public function metricas(Empresa $empresa): array
    {
        return [
            'nao_lidas' => $this->repo->countNaoLidas($empresa),
            'triagem_pendente' => $this->repo->countTriagemPendente($empresa),
        ];
    }

    public function marcarLida(JuridicoPublicacao $publicacao, bool $lida = true): void
    {
        $publicacao->setLidaEm($lida ? new \DateTimeImmutable() : null);
        if ($lida && $publicacao->getStatus() === JuridicoPublicacao::STATUS_NAO_LIDA) {
            $publicacao->setStatus(JuridicoPublicacao::STATUS_TRIAGEM);
        }
        $publicacao->touch();
        $this->em->flush();
    }

    public function arquivar(JuridicoPublicacao $publicacao): void
    {
        $publicacao->setStatus(JuridicoPublicacao::STATUS_ARQUIVADA)->touch();
        $this->em->flush();
    }

    public function triarComIa(JuridicoPublicacao $publicacao, ?User $usuario = null): void
    {
        $resultado = $this->triagem->triar($publicacao);
        if ($resultado === null) {
            throw new JuridicoProcessException('Sasha indisponível para triagem. Tente novamente em instantes.');
        }

        $this->triagem->aplicarResultado($publicacao, $resultado);
        if ($usuario !== null) {
            $publicacao->setTriadaPor($usuario);
        }
        $this->em->flush();

        $this->tentarCriarPrazoAutomatico($publicacao);
    }

    public function vincularProcesso(JuridicoPublicacao $publicacao, int $processoId): void
    {
        $processo = $this->processoRepo->findOneByEmpresa($publicacao->getEmpresa(), $processoId);
        if ($processo === null) {
            throw new JuridicoProcessException('Processo não encontrado.');
        }

        $publicacao->setProcesso($processo);
        if ($processo->getCliente() !== null) {
            $publicacao->setCliente($processo->getCliente());
        }
        $this->matching->aplicar($publicacao);
        $publicacao->setStatus(JuridicoPublicacao::STATUS_VINCULADA)->touch();
        $this->em->flush();

        $this->tentarCriarPrazoAutomatico($publicacao);
    }

    /**
     * Cria prazo automaticamente quando a config do escritório permite e há sugestão da Sasha.
     */
    public function tentarCriarPrazoAutomatico(JuridicoPublicacao $publicacao): bool
    {
        if ($publicacao->isPrazoCriado() || $publicacao->isCancelada()) {
            return false;
        }

        $config = $this->configRepo->getOrCreate($publicacao->getEmpresa());
        if (!$config->isPrazoAutomatico()) {
            return false;
        }

        $dias = $publicacao->getIaSugestaoPrazoDias();
        if ($dias === null || $dias < 1) {
            return false;
        }

        // Só abre prazo automático se já houver processo vinculado (evita prazo órfão).
        if ($publicacao->getProcesso() === null) {
            return false;
        }

        try {
            $this->criarPrazoSugerido($publicacao);

            return true;
        } catch (JuridicoProcessException) {
            return false;
        }
    }

    /** @param array<string, mixed> $data */
    public function criarPrazoSugerido(JuridicoPublicacao $publicacao, array $data = []): void
    {
        if ($publicacao->isPrazoCriado()) {
            throw new JuridicoProcessException('Já existe prazo vinculado a esta publicação.');
        }

        $tipo = trim((string) ($data['tipo'] ?? $publicacao->getIaSugestaoTipoPrazo() ?? 'Manifestação'));
        $dias = (int) ($data['prazo_dias'] ?? $publicacao->getIaSugestaoPrazoDias() ?? 15);
        $dataLimite = DateNormalizer::fromFormDate($data['data_limite'] ?? null)
            ?? (new \DateTimeImmutable('today'))->modify('+' . max(1, $dias) . ' days');

        $descricao = trim((string) ($data['descricao'] ?? ''));
        if ($descricao === '') {
            $descricao = 'Gerado automaticamente a partir de publicação DJEN';
            if ($publicacao->getIaResumo()) {
                $descricao .= ': ' . mb_substr($publicacao->getIaResumo(), 0, 200);
            }
        }

        $this->prazoService->create($publicacao->getEmpresa(), [
            'tipo' => $tipo,
            'descricao' => $descricao,
            'data_limite' => $dataLimite->format('Y-m-d'),
            'processo_id' => $publicacao->getProcesso()?->getId() ?? 0,
            'responsavel_id' => $publicacao->getProcesso()?->getResponsavel()?->getId() ?? 0,
        ]);

        $publicacao->setPrazoCriado(true)->touch();
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    public function criarManual(Empresa $empresa, array $data): JuridicoPublicacao
    {
        $texto = trim((string) ($data['texto'] ?? ''));
        if ($texto === '') {
            throw new JuridicoProcessException('Informe o texto da publicação.');
        }

        $numero = trim((string) ($data['numero_processo'] ?? ''));

        $pub = new JuridicoPublicacao();
        $pub->setEmpresa($empresa);
        $pub->setFonte(JuridicoPublicacao::FONTE_MANUAL);
        $pub->setNumeroProcesso($numero !== '' ? CnjNumeroNormalizer::formatarMascara($numero) ?? $numero : null);
        $pub->setNumeroProcessoNorm(CnjNumeroNormalizer::apenasDigitos($numero));
        $pub->setTexto($texto);
        $pub->setTipoComunicacao($this->nullIfEmpty($data['tipo_comunicacao'] ?? null));
        $pub->setTipoDocumento($this->nullIfEmpty($data['tipo_documento'] ?? null));
        $pub->setTribunal($this->nullIfEmpty($data['tribunal'] ?? null));
        $pub->setDataDisponibilizacao(DateNormalizer::fromFormDate($data['data_disponibilizacao'] ?? null) ?? new \DateTimeImmutable('today'));

        $this->matching->aplicar($pub);
        $this->em->persist($pub);
        $this->em->flush();

        return $pub;
    }

    /**
     * @return array{content: string, content_type: string, filename: string}
     */
    public function baixarCertidao(JuridicoPublicacao $publicacao): array
    {
        $hash = trim((string) $publicacao->getHash());
        if ($hash === '') {
            throw new JuridicoProcessException('Esta publicação não possui hash DJEN para emitir certidão.');
        }

        $pdf = $this->djen->baixarCertidao($hash);
        if ($pdf === null) {
            throw new JuridicoProcessException('Não foi possível obter a certidão PDF no DJEN. Tente novamente em instantes.');
        }

        $numero = $publicacao->getNumeroProcessoNorm() ?? (string) $publicacao->getId();

        return [
            'content' => $pdf['content'],
            'content_type' => $pdf['content_type'],
            'filename' => sprintf('certidao-djen-%s.pdf', $numero),
        ];
    }

    /** @param array<string, mixed> $data */
    public function salvarConfig(Empresa $empresa, array $data): void
    {
        $config = $this->configRepo->getOrCreate($empresa);
        $config->setPrazoAutomatico((bool) ($data['prazo_automatico'] ?? false));
        $config->setAlertaWhatsapp((bool) ($data['alerta_whatsapp'] ?? false));

        $telefone = preg_replace('/\D+/', '', (string) ($data['telefone_alerta'] ?? '')) ?? '';
        $config->setTelefoneAlerta($telefone !== '' ? $telefone : null);
        $config->touch();
        $this->em->flush();
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
