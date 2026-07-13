<?php

namespace App\Service\Organismo\Runtime;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicConta;
use App\Entity\Empresa;
use App\Repository\ClinicAgendamentoRepository;
use App\Repository\ClinicContaRepository;
use App\Repository\ClinicCheckinRepository;
use App\Repository\Organismo\OrganismoCareContractRepository;
use App\Repository\Organismo\OrganismoMemoryFactRepository;
use App\Service\Clinic\ClinicDayPanelService;
use App\Service\Organismo\ClinicNavBadgeService;

/** Lê sensores clínicos reais e devolve vitais por órgão. */
final class VitalReader
{
    public function __construct(
        private ClinicNavBadgeService $badges,
        private ClinicDayPanelService $dayPanel,
        private ClinicContaRepository $contas,
        private ClinicAgendamentoRepository $agendamentos,
        private ClinicCheckinRepository $checkins,
        private OrganismoCareContractRepository $contracts,
        private OrganismoMemoryFactRepository $memory,
        private OrganRegistry $organs,
    ) {
    }

    /**
     * @return array{
     *   badges: array<string, int>,
     *   day: array<string, mixed>,
     *   organs: array<string, array{score: int, signals: list<string>, raw: array<string, int|float>}>
     * }
     */
    public function read(Empresa $empresa): array
    {
        $badges = $this->badges->forEmpresa($empresa);
        $day = $this->dayPanel->build($empresa);
        $contasAbertas = $this->contas->countByEmpresaAndStatus($empresa, ClinicConta::STATUS_ABERTO);
        $hoje = new \DateTimeImmutable('today');
        $amanha = $hoje->modify('+1 day');
        $agendaHoje = $this->countAgendaBetween($empresa, $hoje, $amanha);
        $agendaSemConfirmacao = $this->countAgendaSemConfirmacao($empresa, $hoje, $amanha);
        $faltas7d = $this->countFaltasSince($empresa, $hoje->modify('-7 days'));
        $checkinsHoje = \count($this->checkins->findTodayByEmpresa($empresa));
        $contratosAtivos = \count($this->contracts->findActiveByEmpresa($empresa, 200));
        $memFacts = \count($this->memory->findRecent($empresa, 50));
        $semQuestionario = \count($day['sem_questionario'] ?? []);

        $organs = [];
        foreach ($this->organs->all() as $organ) {
            $id = $organ['id'];
            $organs[$id] = match ($id) {
                OrganRegistry::MONITORAMENTO => $this->scoreMonitoramento($badges),
                OrganRegistry::AGENDA => $this->scoreAgenda($agendaHoje, $agendaSemConfirmacao, $faltas7d),
                OrganRegistry::EXPERIENCIA => $this->scoreExperiencia($semQuestionario, $checkinsHoje, (int) ($badges['pacientes_ativos'] ?? 0)),
                OrganRegistry::FINANCEIRO => $this->scoreFinanceiro($contasAbertas),
                OrganRegistry::MEMORIA => $this->scoreMemoria($contratosAtivos, $memFacts, (int) ($badges['pacientes_ativos'] ?? 0)),
                default => ['score' => 100, 'signals' => [], 'raw' => []],
            };
        }

        return [
            'badges' => $badges,
            'day' => $day,
            'organs' => $organs,
            'raw' => [
                'contas_abertas' => $contasAbertas,
                'agenda_hoje' => $agendaHoje,
                'agenda_sem_confirmacao' => $agendaSemConfirmacao,
                'faltas_7d' => $faltas7d,
                'checkins_hoje' => $checkinsHoje,
                'contratos_ativos' => $contratosAtivos,
                'sem_questionario' => $semQuestionario,
            ],
        ];
    }

    /** @param array<string, int> $badges @return array{score: int, signals: list<string>, raw: array<string, int>} */
    private function scoreMonitoramento(array $badges): array
    {
        $p1 = (int) ($badges['sala_critica'] ?? 0);
        $alertas = (int) ($badges['alertas'] ?? 0);
        $penalty = min(90, ($p1 * 25) + ($alertas * 8));
        $signals = [];
        if ($p1 > 0) {
            $signals[] = sprintf('%d alerta(s) P1 na sala crítica', $p1);
        }
        if ($alertas > 0) {
            $signals[] = sprintf('%d alerta(s) clínicos abertos', $alertas);
        }

        return [
            'score' => 100 - $penalty,
            'signals' => $signals,
            'raw' => ['p1' => $p1, 'alertas' => $alertas],
        ];
    }

    /** @return array{score: int, signals: list<string>, raw: array<string, int>} */
    private function scoreAgenda(int $hoje, int $semConfirmacao, int $faltas): array
    {
        $penalty = min(80, ($semConfirmacao * 12) + ($faltas * 10));
        $signals = [];
        if ($semConfirmacao > 0) {
            $signals[] = sprintf('%d horário(s) sem confirmação hoje', $semConfirmacao);
        }
        if ($faltas > 0) {
            $signals[] = sprintf('%d falta(s) nos últimos 7 dias', $faltas);
        }

        return [
            'score' => 100 - $penalty,
            'signals' => $signals,
            'raw' => ['hoje' => $hoje, 'sem_confirmacao' => $semConfirmacao, 'faltas_7d' => $faltas],
        ];
    }

    /** @return array{score: int, signals: list<string>, raw: array<string, int>} */
    private function scoreExperiencia(int $semQ, int $checkins, int $ativos): array
    {
        $ratio = $ativos > 0 ? $semQ / $ativos : 0.0;
        $penalty = min(85, (int) round($ratio * 70) + ($semQ * 4));
        $signals = [];
        if ($semQ > 0) {
            $signals[] = sprintf('%d paciente(s) sem questionário hoje', $semQ);
        }
        if ($checkins === 0 && $ativos > 0) {
            $signals[] = 'Nenhum check-in registrado hoje';
            $penalty = min(90, $penalty + 8);
        }

        return [
            'score' => 100 - $penalty,
            'signals' => $signals,
            'raw' => ['sem_questionario' => $semQ, 'checkins' => $checkins, 'ativos' => $ativos],
        ];
    }

    /** @return array{score: int, signals: list<string>, raw: array<string, int>} */
    private function scoreFinanceiro(int $abertas): array
    {
        $penalty = min(70, $abertas * 9);
        $signals = [];
        if ($abertas > 0) {
            $signals[] = sprintf('%d conta(s) particular(es) em aberto', $abertas);
        }

        return [
            'score' => 100 - $penalty,
            'signals' => $signals,
            'raw' => ['contas_abertas' => $abertas],
        ];
    }

    /** @return array{score: int, signals: list<string>, raw: array<string, int>} */
    private function scoreMemoria(int $contratos, int $facts, int $ativos): array
    {
        $coverage = $ativos > 0 ? $contratos / $ativos : 1.0;
        $penalty = (int) round((1 - min(1, $coverage)) * 40);
        if ($facts < 3 && $ativos > 0) {
            $penalty += 15;
        }
        $signals = [];
        if ($coverage < 0.5 && $ativos > 0) {
            $signals[] = 'Poucos contratos de cuidado ativos vs pacientes';
        }
        if ($facts < 3) {
            $signals[] = 'Memória do organismo ainda rasa';
        }

        return [
            'score' => 100 - min(80, $penalty),
            'signals' => $signals,
            'raw' => ['contratos' => $contratos, 'facts' => $facts, 'ativos' => $ativos],
        ];
    }

    private function countAgendaBetween(Empresa $empresa, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->agendamentos->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.inicio >= :from')
            ->andWhere('a.inicio < :to')
            ->andWhere('a.status NOT IN (:skip)')
            ->setParameter('empresa', $empresa)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('skip', [ClinicAgendamento::STATUS_CANCELADO])
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countAgendaSemConfirmacao(Empresa $empresa, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->agendamentos->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.inicio >= :from')
            ->andWhere('a.inicio < :to')
            ->andWhere('a.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('status', ClinicAgendamento::STATUS_MARCADO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countFaltasSince(Empresa $empresa, \DateTimeImmutable $since): int
    {
        return (int) $this->agendamentos->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.inicio >= :since')
            ->andWhere('a.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('since', $since)
            ->setParameter('status', ClinicAgendamento::STATUS_FALTOU)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
