<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhEsocialLote;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\RhEsocialLoteRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhEsocialService
{
    private const TIPOS_VALIDOS = ['S1200', 'S1210', 'S1299'];

    public function __construct(
        private EntityManagerInterface $em,
        private RhEsocialLoteRepository $repo,
        private FuncionarioRepository $funcionarioRepo,
        private RhEsocialGateway $gateway,
        private RhAuditService $audit,
    ) {}

    /** @return list<RhEsocialLote> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findForEmpresa($empresa);
    }

    /**
     * @return array{pendente: int, processando: int, enviado: int, erro: int}
     */
    public function queueSummary(Empresa $empresa): array
    {
        return $this->repo->countByStatusGrouped($empresa);
    }

    public function createLote(Empresa $empresa, string $referencia, string $tipoEvento, ?User $actor = null): RhEsocialLote
    {
        $referencia = trim($referencia);
        $tipoEvento = strtoupper(trim($tipoEvento));

        if (!preg_match('/^\d{4}-\d{2}$/', $referencia)) {
            throw new RhProcessException('Informe a competência no formato AAAA-MM.');
        }

        if (!in_array($tipoEvento, self::TIPOS_VALIDOS, true)) {
            throw new RhProcessException('Tipo de evento não suportado.');
        }

        if ($this->repo->findPendingLote($empresa, $referencia, $tipoEvento) !== null) {
            throw new RhProcessException('Já existe um lote pendente para esta competência e evento.');
        }

        $headcount = $this->funcionarioRepo->countByStatusGrouped($empresa);
        $ativos = $headcount['ATIVO'] ?? 0;

        $lote = new RhEsocialLote();
        $lote->setEmpresa($empresa);
        $lote->setReferencia($referencia);
        $lote->setTipoEvento($tipoEvento);
        $lote->setStatus(RhEsocialLote::STATUS_PENDENTE);
        $lote->setPayload($this->buildPayload($empresa, $referencia, $tipoEvento, $ativos));

        $this->em->persist($lote);
        $this->em->flush();

        $this->audit->log($empresa, $actor, 'esocial', 'criar_lote', 'rh_esocial_lote', $lote->getId());

        return $lote;
    }

    /**
     * @return array{processados: int, enviados: int, erros: int}
     */
    public function processQueue(Empresa $empresa, int $limit = 10, ?User $actor = null): array
    {
        $lotes = $this->repo->findNextInQueue($empresa, $limit);
        $stats = ['processados' => 0, 'enviados' => 0, 'erros' => 0];

        foreach ($lotes as $lote) {
            if ($lote->getEmpresa()->getId() !== $empresa->getId()) {
                continue;
            }
            $result = $this->processOne($lote, $actor);
            ++$stats['processados'];
            if ($result === RhEsocialLote::STATUS_ENVIADO) {
                ++$stats['enviados'];
            } elseif ($result === RhEsocialLote::STATUS_ERRO) {
                ++$stats['erros'];
            }
        }

        return $stats;
    }

    /**
     * Processa fila global (cron / console).
     *
     * @return array{processados: int, enviados: int, erros: int}
     */
    public function processGlobalQueue(int $limit = 50): array
    {
        $stats = ['processados' => 0, 'enviados' => 0, 'erros' => 0];

        foreach ($this->repo->findNextInQueue(null, $limit) as $lote) {
            $result = $this->processOne($lote, null);
            ++$stats['processados'];
            if ($result === RhEsocialLote::STATUS_ENVIADO) {
                ++$stats['enviados'];
            } elseif ($result === RhEsocialLote::STATUS_ERRO) {
                ++$stats['erros'];
            }
        }

        return $stats;
    }

    public function retryLote(RhEsocialLote $lote, ?User $actor = null): void
    {
        if (!$lote->canRetry()) {
            throw new RhProcessException('Este lote não pode ser reenviado (limite de tentativas ou status inválido).');
        }

        $lote->setStatus(RhEsocialLote::STATUS_PENDENTE);
        $lote->setUltimoErro(null);
        $this->em->flush();

        $this->audit->log($lote->getEmpresa(), $actor, 'esocial', 'retry_lote', 'rh_esocial_lote', $lote->getId());
    }

    private function processOne(RhEsocialLote $lote, ?User $actor): string
    {
        if (!in_array($lote->getStatus(), [RhEsocialLote::STATUS_PENDENTE, RhEsocialLote::STATUS_ERRO], true)) {
            return $lote->getStatus();
        }

        if ($lote->getStatus() === RhEsocialLote::STATUS_ERRO && $lote->getTentativas() >= RhEsocialLote::MAX_TENTATIVAS) {
            return $lote->getStatus();
        }

        $lote->setStatus(RhEsocialLote::STATUS_PROCESSANDO);
        $lote->setTentativas($lote->getTentativas() + 1);
        $this->em->flush();

        $payload = $lote->getPayload() ?? [];
        $result = $this->gateway->transmit($lote, $payload);

        if ($result['ok']) {
            $lote->setStatus(RhEsocialLote::STATUS_ENVIADO);
            $lote->setProtocolo($result['protocolo'] ?? null);
            $lote->setEnviadoEm(new \DateTimeImmutable());
            $lote->setUltimoErro(null);
            $payload['resposta_gov'] = $result['resposta'] ?? null;
            $lote->setPayload($payload);

            $this->audit->log($lote->getEmpresa(), $actor, 'esocial', 'enviar_lote', 'rh_esocial_lote', $lote->getId());
        } else {
            $lote->setStatus(RhEsocialLote::STATUS_ERRO);
            $lote->setUltimoErro(mb_substr((string) ($result['erro'] ?? 'Falha no envio.'), 0, 500));
            $payload['ultima_falha'] = $result['erro'] ?? null;
            $lote->setPayload($payload);

            $this->audit->log($lote->getEmpresa(), $actor, 'esocial', 'erro_lote', 'rh_esocial_lote', $lote->getId());
        }

        $this->em->flush();

        return $lote->getStatus();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Empresa $empresa, string $referencia, string $tipoEvento, int $trabalhadores): array
    {
        return [
            'empresa_id' => $empresa->getId(),
            'competencia' => $referencia,
            'evento' => $tipoEvento,
            'trabalhadores' => $trabalhadores,
            'gerado_em' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'versao_layout' => 'S-1.3',
            'ambiente' => 'homologacao',
        ];
    }
}
