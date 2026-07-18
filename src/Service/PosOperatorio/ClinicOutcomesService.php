<?php

namespace App\Service\PosOperatorio;

use App\Entity\Crm\CrmLead;
use App\Entity\Empresa;
use App\Entity\PosOperatorioAlerta;
use App\Entity\PosOperatorioPaciente;
use App\Entity\PosOperatorioQuestionarioResposta;
use App\Entity\User;
use App\Repository\Crm\CrmLeadRepository;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioPacienteRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unio Outcomes™ — inteligência de resultado clínico (MVP).
 *
 * Camadas: score preditivo · índice por cirurgião · loop receita ↔ cuidado.
 */
final class ClinicOutcomesService
{
    public function __construct(
        private PosOperatorioPacienteRepository $pacientes,
        private PosOperatorioAlertaRepository $alertas,
        private CrmLeadRepository $crmLeads,
    ) {}

    /**
     * @return array{
     *     kpis: list<array{value: string, label: string, icon: string, tone: string}>,
     *     risk_patients: list<array<string, mixed>>,
     *     surgeons: list<array<string, mixed>>,
     *     revenue_loop: array<string, mixed>,
     *     medico_filter: int|null,
     *     medicos: list<array{id: int, nome: string}>
     * }
     */
    public function buildDashboard(Empresa $empresa, ?int $medicoId = null): array
    {
        $patients = $this->pacientes->findRecentByEmpresa($empresa, 300, 0);
        if ($medicoId !== null) {
            $patients = array_values(array_filter(
                $patients,
                static fn (PosOperatorioPaciente $p): bool => (int) ($p->getMedicoResponsavel()?->getId() ?? 0) === $medicoId,
            ));
        }

        $riskPatients = [];
        $surgeonBuckets = [];

        foreach ($patients as $paciente) {
            $risk = $this->computePatientRisk($paciente);
            $proms = $this->extractProms($paciente);
            $medico = $paciente->getMedicoResponsavel();
            $medicoKey = $medico?->getId() ?? 0;

            $riskPatients[] = [
                'id' => $paciente->getId(),
                'nome' => $paciente->getNome(),
                'codigo' => $paciente->getCodigo(),
                'procedimento' => $paciente->getProcedimento() ?? '—',
                'dia_pos' => $paciente->getDiaPosOperatorio(),
                'medico' => $medico?->getNome() ?? 'Não definido',
                'medico_id' => $medicoKey > 0 ? $medicoKey : null,
                'score' => $risk['score'],
                'nivel' => $risk['nivel'],
                'nivel_label' => $risk['nivel_label'],
                'fatores' => $risk['fatores'],
                'adesao_pct' => $proms['adesao_pct'],
                'dor_media' => $proms['dor_media'],
                'satisfacao' => $proms['satisfacao'],
                'alertas_p1' => $risk['alertas_p1'],
            ];

            if ($medicoKey > 0) {
                $surgeonBuckets[$medicoKey] ??= [
                    'id' => $medicoKey,
                    'nome' => (string) ($medico?->getNome() ?? 'Médico'),
                    'pacientes' => 0,
                    'scores' => [],
                    'adesoes' => [],
                    'dores' => [],
                    'satisfacoes' => [],
                    'p1_total' => 0,
                    'procedimentos' => [],
                ];
                $surgeonBuckets[$medicoKey]['pacientes']++;
                $surgeonBuckets[$medicoKey]['scores'][] = $risk['score'];
                $surgeonBuckets[$medicoKey]['adesoes'][] = $proms['adesao_pct'];
                if ($proms['dor_media'] !== null) {
                    $surgeonBuckets[$medicoKey]['dores'][] = $proms['dor_media'];
                }
                if ($proms['satisfacao'] !== null) {
                    $surgeonBuckets[$medicoKey]['satisfacoes'][] = $proms['satisfacao'];
                }
                $surgeonBuckets[$medicoKey]['p1_total'] += $risk['alertas_p1'];
                $proc = $paciente->getProcedimento() ?? '—';
                $surgeonBuckets[$medicoKey]['procedimentos'][$proc] = ($surgeonBuckets[$medicoKey]['procedimentos'][$proc] ?? 0) + 1;
            }
        }

        usort($riskPatients, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $highRisk = \count(array_filter($riskPatients, static fn (array $r): bool => $r['nivel'] === 'alto'));
        $riskChart = ['alto' => 0, 'moderado' => 0, 'baixo' => 0];
        foreach ($riskPatients as $rp) {
            $nivel = $rp['nivel'];
            if (isset($riskChart[$nivel])) {
                ++$riskChart[$nivel];
            }
        }
        $riskPatientsTop = \array_slice($riskPatients, 0, 25);

        $surgeons = [];
        foreach ($surgeonBuckets as $bucket) {
            $avgRisk = $this->avg($bucket['scores']);
            $avgAdesao = (int) round($this->avg($bucket['adesoes']));
            $avgDor = $bucket['dores'] !== [] ? round($this->avg($bucket['dores']), 1) : null;
            $avgSat = $bucket['satisfacoes'] !== [] ? (int) round($this->avg($bucket['satisfacoes'])) : null;
            $outcomeIndex = $this->computeOutcomeIndex($avgAdesao, $avgRisk, $avgSat, $bucket['p1_total'], $bucket['pacientes']);

            arsort($bucket['procedimentos']);
            $topProc = array_key_first($bucket['procedimentos']) ?? '—';

            $surgeons[] = [
                'id' => $bucket['id'],
                'nome' => $bucket['nome'],
                'pacientes' => $bucket['pacientes'],
                'adesao_pct' => $avgAdesao,
                'dor_media' => $avgDor,
                'satisfacao' => $avgSat,
                'alertas_p1' => $bucket['p1_total'],
                'risco_medio' => (int) round($avgRisk),
                'outcome_index' => $outcomeIndex,
                'procedimento_top' => $topProc,
            ];
        }

        usort($surgeons, static fn (array $a, array $b): int => $b['outcome_index'] <=> $a['outcome_index']);

        $revenueLoop = $this->buildRevenueLoop($empresa, $patients);
        $avgOutcome = $surgeons !== [] ? (int) round($this->avg(array_column($surgeons, 'outcome_index'))) : 0;

        return [
            'kpis' => [
                ['value' => (string) \count($patients), 'value_num' => \count($patients), 'label' => 'Pacientes monitorados', 'icon' => 'fa-user-injured', 'tone' => 'sky'],
                array_merge(
                    ['value' => $avgOutcome > 0 ? $avgOutcome.'%' : '—', 'label' => 'Índice Outcomes médio', 'icon' => 'fa-chart-line', 'tone' => 'sage'],
                    $avgOutcome > 0 ? ['value_num' => $avgOutcome, 'value_suffix' => '%'] : []
                ),
                ['value' => (string) $highRisk, 'value_num' => $highRisk, 'label' => 'Risco elevado', 'icon' => 'fa-triangle-exclamation', 'tone' => 'rose'],
                ['value' => ($revenueLoop['crm_conversao_pct'] ?? 0).'%', 'value_num' => (int) ($revenueLoop['crm_conversao_pct'] ?? 0), 'value_suffix' => '%', 'label' => 'CRM → paciente', 'icon' => 'fa-handshake', 'tone' => 'lavender'],
            ],
            'risk_chart' => [
                'labels' => ['Alto', 'Moderado', 'Baixo'],
                'values' => [$riskChart['alto'], $riskChart['moderado'], $riskChart['baixo']],
                'total' => $riskChart['alto'] + $riskChart['moderado'] + $riskChart['baixo'],
            ],
            'risk_patients' => $riskPatientsTop,
            'risk_patients_all' => $riskPatients,
            'surgeons' => $surgeons,
            'revenue_loop' => $revenueLoop,
            'medico_filter' => $medicoId,
            'medicos' => $this->listMedicos($empresa, $patients),
        ];
    }

    /**
     * @return array{
     *     score: int,
     *     nivel: string,
     *     nivel_label: string,
     *     fatores: list<string>,
     *     alertas_p1: int
     * }
     */
    public function computePatientRisk(PosOperatorioPaciente $paciente): array
    {
        $fatores = [];
        $latestQr = $paciente->getUltimaResposta();
        $qrScore = $latestQr instanceof PosOperatorioQuestionarioResposta ? $latestQr->getScoreRisco() : 0;
        if ($qrScore > 0) {
            $fatores[] = sprintf('Questionário: score %d', $qrScore);
        }

        $alertWeight = 0;
        $p1Count = 0;
        foreach ($paciente->getAlertas() as $alerta) {
            if ($alerta->getStatus() === PosOperatorioAlerta::STATUS_RESOLVIDO) {
                continue;
            }
            $w = match ($alerta->getPrioridade()) {
                'P1' => 25,
                'P2' => 18,
                'P3' => 10,
                default => 5,
            };
            $alertWeight = max($alertWeight, $w);
            if ($alerta->getPrioridade() === 'P1') {
                ++$p1Count;
            }
        }
        if ($alertWeight > 0) {
            $fatores[] = 'Alerta clínico em aberto';
        }

        $proms = $this->extractProms($paciente);
        $adherencePenalty = max(0, 100 - $proms['adesao_pct']);
        $adherenceComponent = (int) round($adherencePenalty * 0.2);
        if ($adherenceComponent >= 10) {
            $fatores[] = sprintf('Adesão baixa (%d%%)', $proms['adesao_pct']);
        }

        $earlyBump = 0;
        $diaPos = $paciente->getDiaPosOperatorio();
        if ($diaPos !== null && $diaPos <= 3) {
            $earlyBump = 15 - ($diaPos * 3);
            $fatores[] = sprintf('Pós-op recente (D+%d)', $diaPos);
        }

        if ($paciente->getStatus() === PosOperatorioPaciente::STATUS_ALERTA) {
            $fatores[] = 'Status em alerta';
        }

        $score = (int) min(100, max(0, round(
            ($qrScore * 0.4)
            + $alertWeight
            + $adherenceComponent
            + $earlyBump
            + ($paciente->getStatus() === PosOperatorioPaciente::STATUS_ALERTA ? 8 : 0),
        )));

        [$nivel, $nivelLabel] = $this->riskLevel($score);

        return [
            'score' => $score,
            'nivel' => $nivel,
            'nivel_label' => $nivelLabel,
            'fatores' => $fatores !== [] ? $fatores : ['Sem sinais de risco relevantes'],
            'alertas_p1' => $p1Count,
        ];
    }

    /** @return array{adesao_pct: int, dor_media: ?float, satisfacao: ?int} */
    public function extractProms(PosOperatorioPaciente $paciente): array
    {
        $diaPos = $paciente->getDiaPosOperatorio();
        $expected = $diaPos !== null && $diaPos > 0 ? min($diaPos, 30) : 0;
        $actual = $paciente->getQuestionarios()->count();
        $adesao = $expected > 0 ? (int) min(100, round(($actual / $expected) * 100)) : ($actual > 0 ? 100 : 0);

        $dores = [];
        $satisfacoes = [];
        foreach ($paciente->getQuestionarios() as $qr) {
            $r = $qr->getRespostas();
            $dor = (float) ($r['dor'] ?? $r['nivel_dor'] ?? -1);
            if ($dor >= 0) {
                $dores[] = $dor;
            }
            $sat = $r['satisfacao'] ?? $r['nps'] ?? $r['como_se_sente'] ?? null;
            if (is_numeric($sat)) {
                $satisfacoes[] = (int) $sat;
            } elseif (is_string($sat) && is_numeric(trim($sat))) {
                $satisfacoes[] = (int) trim($sat);
            }
        }

        $latestQr = $paciente->getUltimaResposta();
        $satisfacao = $satisfacoes !== []
            ? (int) round($this->avg($satisfacoes))
            : ($latestQr instanceof PosOperatorioQuestionarioResposta
                ? max(0, min(100, 100 - $latestQr->getScoreRisco()))
                : null);

        return [
            'adesao_pct' => $adesao,
            'dor_media' => $dores !== [] ? round($this->avg($dores), 1) : null,
            'satisfacao' => $satisfacao,
        ];
    }

    public function exportCsv(Empresa $empresa, ?int $medicoId = null): StreamedResponse
    {
        $dashboard = $this->buildDashboard($empresa, $medicoId);
        $rows = $dashboard['risk_patients_all'];

        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Paciente', 'Código', 'Cirurgião', 'Procedimento', 'Dia pós-op',
                'Score risco', 'Nível', 'Adesão %', 'Dor média', 'Satisfação', 'Alertas P1',
            ], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['nome'],
                    $row['codigo'],
                    $row['medico'],
                    $row['procedimento'],
                    $row['dia_pos'] !== null ? 'D+'.$row['dia_pos'] : '—',
                    (string) $row['score'],
                    $row['nivel_label'],
                    (string) $row['adesao_pct'],
                    $row['dor_media'] !== null ? (string) $row['dor_media'] : '',
                    $row['satisfacao'] !== null ? (string) $row['satisfacao'] : '',
                    (string) $row['alertas_p1'],
                ], ';');
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf(
            'attachment; filename="unio-outcomes-%s.csv"',
            date('Y-m-d'),
        ));

        return $response;
    }

    /** @param list<PosOperatorioPaciente> $patients */
    private function buildRevenueLoop(Empresa $empresa, array $patients): array
    {
        $leadsTotal = $this->crmLeads->countByEmpresa($empresa);
        $leadsConvertidos = $this->crmLeads->countByEmpresa($empresa, CrmLead::STATUS_CONVERTIDO);
        $leadsIndicacao = 0;
        foreach ($this->crmLeads->findByEmpresa($empresa, null, 500) as $lead) {
            if ($lead->getOrigem() === CrmLead::ORIGEM_INDICACAO) {
                ++$leadsIndicacao;
            }
        }

        $comIndicacao = 0;
        $encerradosOk = 0;
        foreach ($patients as $p) {
            if ($p->getIndicadoPor() !== null && trim($p->getIndicadoPor()) !== '') {
                ++$comIndicacao;
            }
            if ($p->getStatus() === PosOperatorioPaciente::STATUS_ENCERRADO) {
                ++$encerradosOk;
            }
        }

        $crmConv = $leadsTotal > 0 ? (int) round(($leadsConvertidos / $leadsTotal) * 100) : 0;

        return [
            'crm_leads' => $leadsTotal,
            'crm_convertidos' => $leadsConvertidos,
            'crm_conversao_pct' => $crmConv,
            'crm_indicacao' => $leadsIndicacao,
            'pacientes_indicados' => $comIndicacao,
            'funil' => [
                ['etapa' => 'Leads CRM', 'valor' => $leadsTotal, 'icon' => 'fa-bullseye'],
                ['etapa' => 'Convertidos', 'valor' => $leadsConvertidos, 'icon' => 'fa-user-check'],
                ['etapa' => 'Em trilha', 'valor' => \count($patients), 'icon' => 'fa-route'],
                ['etapa' => 'Indicações registradas', 'valor' => $comIndicacao, 'icon' => 'fa-share-nodes'],
            ],
            'roi_narrativa' => $crmConv > 0
                ? sprintf('%d%% dos leads viraram pacientes monitorados com outcomes mensuráveis.', $crmConv)
                : 'Conecte o CRM para fechar o funil lead → cirurgia → resultado.',
        ];
    }

    /**
     * @param list<PosOperatorioPaciente> $patients
     *
     * @return list<array{id: int, nome: string}>
     */
    private function listMedicos(Empresa $empresa, array $patients): array
    {
        $map = [];
        foreach ($patients as $p) {
            $m = $p->getMedicoResponsavel();
            if (!$m instanceof User || $m->getId() === null) {
                continue;
            }
            $map[(int) $m->getId()] = (string) ($m->getNome() ?? $m->getEmail());
        }
        asort($map);
        $list = [];
        foreach ($map as $id => $nome) {
            $list[] = ['id' => $id, 'nome' => $nome];
        }

        return $list;
    }

    private function computeOutcomeIndex(int $adesao, float $avgRisk, ?int $satisfacao, int $p1Total, int $pacientes): int
    {
        $satComponent = $satisfacao ?? max(0, 100 - (int) round($avgRisk));
        $p1Penalty = $pacientes > 0 ? min(25, (int) round(($p1Total / $pacientes) * 20)) : 0;

        return (int) max(0, min(100, round(
            ($adesao * 0.35)
            + ($satComponent * 0.35)
            + ((100 - $avgRisk) * 0.3)
            - $p1Penalty,
        )));
    }

    /** @return array{0: string, 1: string} */
    private function riskLevel(int $score): array
    {
        if ($score >= 67) {
            return ['alto', 'Alto'];
        }
        if ($score >= 34) {
            return ['moderado', 'Moderado'];
        }

        return ['baixo', 'Baixo'];
    }

    /** @param list<int|float> $values */
    private function avg(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / \count($values);
    }
}
