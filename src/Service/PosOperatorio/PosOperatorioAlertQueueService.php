<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioAlerta;
use App\Repository\PosOperatorioAlertaRepository;

/**
 * Fila clínica e Sala Crítica — padrão operacional inspirado no Hub TI.
 */
final class PosOperatorioAlertQueueService
{
    public function __construct(
        private PosOperatorioAlertaRepository $repository,
    ) {}

    /**
     * @return array{
     *     tickets: list<array<string, mixed>>,
     *     stats: array{p1: int, p2: int, abertos: int, em_atendimento: int},
     *     filters: array{prioridade: string|null, status: string|null}
     * }
     */
    public function buildQueue(Empresa $empresa, ?string $prioridade = null, ?string $status = null): array
    {
        $alertas = $this->repository->findQueueByEmpresa($empresa, $prioridade, $status ?? 'ativos');
        $tickets = array_map(fn (PosOperatorioAlerta $a) => $this->mapTicket($a), $alertas);

        $abertos = $this->repository->findAbertosByEmpresa($empresa, 500);

        return [
            'tickets' => $tickets,
            'stats' => [
                'p1' => \count(array_filter($abertos, static fn (PosOperatorioAlerta $a) => $a->getPrioridade() === 'P1')),
                'p2' => \count(array_filter($abertos, static fn (PosOperatorioAlerta $a) => $a->getPrioridade() === 'P2')),
                'abertos' => \count(array_filter($abertos, static fn (PosOperatorioAlerta $a) => $a->getStatus() === PosOperatorioAlerta::STATUS_ABERTO)),
                'em_atendimento' => \count(array_filter($abertos, static fn (PosOperatorioAlerta $a) => $a->getStatus() === PosOperatorioAlerta::STATUS_EM_ATENDIMENTO)),
            ],
            'filters' => ['prioridade' => $prioridade, 'status' => $status],
        ];
    }

    /** @return array<string, mixed> */
    public function buildWarRoom(Empresa $empresa): array
    {
        $p1 = $this->repository->findP1Ativos($empresa);
        $incidents = array_map(fn (PosOperatorioAlerta $a) => $this->mapTicket($a, true), $p1);

        $severity = min(100, \count($p1) * 35 + 10);

        return [
            'p1_incidents' => $incidents,
            'p1_count' => \count($p1),
            'severity_score' => $severity,
            'severity_level' => match (true) {
                $severity >= 70 => 'critical',
                $severity >= 40 => 'elevated',
                \count($p1) > 0 => 'watch',
                default => 'stable',
            },
            'command_brief' => $this->buildBrief($incidents, $severity),
            'stats' => $this->buildQueue($empresa)['stats'],
        ];
    }

    /** @return array{stats: array{p1: int, p2: int, abertos: int, em_atendimento: int}, at: string} */
    public function buildPollSnapshot(Empresa $empresa): array
    {
        $queue = $this->buildQueue($empresa);

        return [
            'stats' => $queue['stats'],
            'p1_count' => $queue['stats']['p1'],
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function mapTicket(PosOperatorioAlerta $a, bool $extended = false): array
    {
        $paciente = $a->getPaciente();
        $sla = $this->slaMeta($a);
        $resp = $a->getResponsavel();

        $row = [
            'id' => $a->getId(),
            'prioridade' => $a->getPrioridade(),
            'motivo' => $a->getMotivo(),
            'status' => $a->getStatus(),
            'status_label' => match ($a->getStatus()) {
                PosOperatorioAlerta::STATUS_EM_ATENDIMENTO => 'Em atendimento',
                PosOperatorioAlerta::STATUS_RESOLVIDO => 'Resolvido',
                default => 'Aberto',
            },
            'paciente_codigo' => $paciente->getCodigo(),
            'paciente_nome' => $paciente->getNome(),
            'paciente_id' => $paciente->getId(),
            'procedimento' => $paciente->getProcedimento() ?? '—',
            'dia_pos' => $paciente->getDiaPosOperatorio(),
            'responsavel' => $resp?->getNome() ?? '—',
            'responsavel_id' => $resp?->getId(),
            'criado_em' => $a->getCriadoEm()->format('d/m H:i'),
            'sla_pct' => $sla['pct'],
            'sla_remaining_label' => $sla['remaining_label'],
            'sla_breach_imminent' => $sla['imminent'],
        ];

        if ($extended) {
            $row['timeline_hint'] = sprintf(
                'Aberto %s · SLA restante %s',
                $row['criado_em'],
                $sla['remaining_label'],
            );
        }

        return $row;
    }

    /** @return array{pct: int|null, remaining_label: string, imminent: bool} */
    private function slaMeta(PosOperatorioAlerta $a): array
    {
        $limite = $a->getSlaLimiteEm();
        if (!$limite) {
            return ['pct' => null, 'remaining_label' => '—', 'imminent' => false];
        }

        $total = max(1, $limite->getTimestamp() - $a->getCriadoEm()->getTimestamp());
        $elapsed = max(0, time() - $a->getCriadoEm()->getTimestamp());
        $remaining = max(0, $limite->getTimestamp() - time());
        $pct = min(100, max(0, (int) round(($elapsed / $total) * 100)));

        $mins = intdiv($remaining, 60);
        $secs = $remaining % 60;

        return [
            'pct' => $pct,
            'remaining_label' => sprintf('%02d:%02d', $mins, $secs),
            'imminent' => $remaining < 900 && $a->getStatus() !== PosOperatorioAlerta::STATUS_RESOLVIDO,
        ];
    }

    /** @param list<array<string, mixed>> $incidents */
    private function buildBrief(array $incidents, int $severity): array
    {
        if ($incidents === []) {
            return [
                'headline' => 'Sala Crítica: nenhum alerta P1 ativo',
                'summary' => 'Acompanhamento clínico dentro dos parâmetros. Fila P2/P3 disponível na aba Alertas.',
                'tone' => 'ok',
            ];
        }

        $worst = $incidents[0];

        return [
            'headline' => \count($incidents) === 1
                ? 'P1 ativo: ' . ($worst['paciente_codigo'] ?? '')
                : \count($incidents) . ' alertas P1 simultâneos',
            'summary' => sprintf(
                'Priorize contato com %s (%s). Motivo: %s. SLA: %s.',
                $worst['paciente_nome'] ?? 'paciente',
                $worst['paciente_codigo'] ?? '',
                $worst['motivo'] ?? '',
                $worst['sla_remaining_label'] ?? '—',
            ),
            'tone' => $severity >= 70 ? 'critical' : 'warn',
        ];
    }
}
