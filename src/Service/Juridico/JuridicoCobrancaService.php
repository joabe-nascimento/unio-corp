<?php

namespace App\Service\Juridico;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\JuridicoCobranca;
use App\Entity\JuridicoHonorarioLancamento;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoClienteRepository;
use App\Repository\JuridicoCobrancaRepository;
use App\Repository\JuridicoHonorarioLancamentoRepository;
use App\Repository\JuridicoProcessoRepository;
use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappService;
use App\Service\PosOperatorio\Whatsapp\WhatsappTemplateLibrary;
use App\Support\BrPersonFormat;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class JuridicoCobrancaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoCobrancaRepository $repo,
        private JuridicoClienteRepository $clienteRepo,
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoHonorarioLancamentoRepository $honorarioRepo,
        private ClinicWhatsappService $whatsapp,
        private LoggerInterface $logger,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data): JuridicoCobranca
    {
        $cobranca = new JuridicoCobranca();
        $cobranca->setEmpresa($empresa);
        $this->applyData($empresa, $cobranca, $data);
        $this->em->persist($cobranca);
        $this->em->flush();

        return $cobranca;
    }

    public function gerarDeLancamento(JuridicoHonorarioLancamento $lancamento, int $diasVencimento = 15): JuridicoCobranca
    {
        if (!$lancamento->isFaturavel()) {
            throw new JuridicoProcessException('Lançamento não é faturável.');
        }

        $existente = $this->repo->findOneBy(['lancamento' => $lancamento]);
        if ($existente instanceof JuridicoCobranca) {
            return $existente;
        }

        $valor = $lancamento->getValorTotal();
        if ($valor <= 0) {
            throw new JuridicoProcessException('Valor do lançamento inválido para cobrança.');
        }

        $descricao = $lancamento->getDescricao()
            ?? sprintf('Honorários — %s', $lancamento->getAdvogado()?->getNome() ?? 'Advogado');

        $cobranca = new JuridicoCobranca();
        $cobranca->setEmpresa($lancamento->getEmpresa());
        $cobranca->setLancamento($lancamento);
        $cobranca->setProcesso($lancamento->getProcesso());
        $cobranca->setCliente($lancamento->getProcesso()?->getCliente());
        $cobranca->setDescricao(mb_substr($descricao, 0, 200));
        $cobranca->setValor(number_format($valor, 2, '.', ''));
        $cobranca->setVencimento((new \DateTimeImmutable('today'))->modify('+' . max(1, $diasVencimento) . ' days'));
        $cobranca->setStatus(JuridicoCobranca::STATUS_PENDENTE);

        $this->em->persist($cobranca);
        $this->em->flush();

        return $cobranca;
    }

    public function marcarPago(JuridicoCobranca $cobranca): void
    {
        $cobranca->setStatus(JuridicoCobranca::STATUS_PAGO)->setPagoEm(new \DateTimeImmutable())->touch();
        $this->em->flush();
    }

    public function cancelar(JuridicoCobranca $cobranca): void
    {
        $cobranca->setStatus(JuridicoCobranca::STATUS_CANCELADO)->touch();
        $this->em->flush();
    }

    public function enviarCobrancaWhatsapp(JuridicoCobranca $cobranca): bool
    {
        $cliente = $cobranca->getCliente();
        $telefone = $cliente?->getTelefone();
        if ($telefone === null || trim($telefone) === '') {
            throw new JuridicoProcessException('Cliente sem telefone cadastrado.');
        }

        if (!$this->whatsapp->isLive()) {
            throw new JuridicoProcessException('WhatsApp Meta não configurado.');
        }

        $texto = WhatsappTemplateLibrary::cobrancaConta(
            $cliente->getNome(),
            $cobranca->getValorFloat(),
            $cobranca->getVencimento(),
            $cobranca->getDescricao(),
        );

        try {
            $this->whatsapp->send($cobranca->getEmpresa(), $telefone, $texto, [
                'event' => 'juridico_cobranca',
                'cobranca_id' => $cobranca->getId(),
            ]);
            $cobranca->setUltimaCobrancaEm(new \DateTimeImmutable())->touch();
            $this->em->flush();

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Falha cobrança WhatsApp: {msg}', ['msg' => $e->getMessage()]);
            throw new JuridicoProcessException('Não foi possível enviar a cobrança por WhatsApp.');
        }
    }

    public function atualizarVencidos(Empresa $empresa): int
    {
        return $this->repo->atualizarVencidos($empresa);
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoCobranca
    {
        $cobranca = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$cobranca) {
            throw new JuridicoProcessException('Cobrança não encontrada.');
        }

        return $cobranca;
    }

    /** @return list<JuridicoCobranca> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        return $this->repo->findForEmpresa($empresa, $status, $q);
    }

    /** @param array<string, mixed> $data */
    private function applyData(Empresa $empresa, JuridicoCobranca $cobranca, array $data): void
    {
        $descricao = trim((string) ($data['descricao'] ?? ''));
        if ($descricao === '') {
            throw new JuridicoProcessException('Informe a descrição da cobrança.');
        }

        $valor = BrPersonFormat::parseMoney($data['valor'] ?? null);
        if ($valor === null || $valor <= 0) {
            throw new JuridicoProcessException('Informe um valor válido.');
        }

        $vencimento = DateNormalizer::fromFormDate($data['vencimento'] ?? null);
        if (!$vencimento) {
            throw new JuridicoProcessException('Informe a data de vencimento.');
        }

        $cobranca->setDescricao($descricao);
        $cobranca->setValor(number_format($valor, 2, '.', ''));
        $cobranca->setVencimento($vencimento);

        $clienteId = (int) ($data['cliente_id'] ?? 0);
        if ($clienteId > 0) {
            $cobranca->setCliente($this->clienteRepo->findOneBy(['id' => $clienteId, 'empresa' => $empresa]));
        }

        $processoId = (int) ($data['processo_id'] ?? 0);
        if ($processoId > 0) {
            $cobranca->setProcesso($this->processoRepo->findOneByEmpresa($empresa, $processoId));
        }
    }
}
