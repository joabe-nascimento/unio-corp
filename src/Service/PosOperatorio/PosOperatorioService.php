<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioAlerta;
use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\PosOperatorio\PosOperatorioDisplay;
use App\PosOperatorio\PosOperatorioTimelineFormatter;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioEventoRepository;
use App\Repository\PosOperatorioPacienteRepository;
use App\Repository\PosOperatorioProtocoloRepository;
use App\Rh\RhProcessDisplay;
use App\Service\WorkspaceService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * UNIO SAÚDE — dashboard pós-operatório (dados reais ou preview ilustrativo).
 */
final class PosOperatorioService
{
    public const PATIENTS_PER_PAGE_DEFAULT = 10;

    /** @var list<int> */
    public const PATIENTS_PER_PAGE_OPTIONS = [10, 15];

    public function __construct(
        private WorkspaceService $workspace,
        private PosOperatorioPacienteRepository $pacienteRepo,
        private PosOperatorioAlertaRepository $alertaRepo,
        private PosOperatorioProtocoloRepository $protocoloRepo,
        private PosOperatorioEventoRepository $eventoRepo,
    ) {}

    /** @return array<string, mixed> */
    public function getDashboard(User $user, int $page = 1, int $perPage = self::PATIENTS_PER_PAGE_DEFAULT): array
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw new BadRequestHttpException('Área de trabalho indisponível.');
        }

        $perPage = $this->normalizePerPage($perPage);
        $totalPatients = $this->pacienteRepo->countRecentByEmpresa($empresa);
        $useRealData = $totalPatients > 0;

        if ($useRealData) {
            return $this->buildRealDashboard($empresa, $user, $page, $perPage, $totalPatients);
        }

        return $this->buildMockDashboard($empresa, $page, $perPage);
    }

    /** @return array<string, mixed> */
    private function buildRealDashboard(Empresa $empresa, User $user, int $page, int $perPage, int $totalPatients): array
    {
        $totalPages = max(1, (int) ceil($totalPatients / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $pacientes = $this->pacienteRepo->findRecentByEmpresa($empresa, $perPage, $offset);
        $alertas = $this->alertaRepo->findAbertosByEmpresa($empresa, 10);
        $alertCount = $this->alertaRepo->countAbertosByEmpresa($empresa);
        $protocolCount = $this->protocoloRepo->countAtivosByEmpresa($empresa);

        $recentPatients = array_map(fn (PosOperatorioPaciente $p) => $this->mapPacienteRow($p), $pacientes);
        $activeAlerts = array_map(fn (PosOperatorioAlerta $a) => $this->mapAlertaRow($a), $alertas);

        $pendentes = array_values(array_filter($recentPatients, static fn (array $r) => $r['status'] === 'pendente'));

        return [
            'empresa' => $empresa,
            'pos_section' => 'overview',
            'pos_dev_mode' => false,
            'pos_pulse' => $this->buildClinicalPulseFromCounts($alertCount, \count($pendentes)),
            'pos_ticker' => $this->buildTickerFromCounts($totalPatients, $alertCount, $pendentes),
            'kpis' => $this->buildKpisFromCounts($totalPatients, $alertCount, $protocolCount),
            'module_cards' => $this->moduleCardsFromCounts($totalPatients, $protocolCount, $alertCount),
            'recent_patients' => $recentPatients,
            'patients_pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalPatients,
            ],
            'patients_per_page_options' => self::PATIENTS_PER_PAGE_OPTIONS,
            'active_alerts' => $activeAlerts,
            'timeline_events' => $this->buildTimelineForEmpresa($empresa),
            'team_online' => [],
            'protocol_phases' => $this->protocolPhasesFromPacientes($pacientes),
        ];
    }

    /** @return array<string, mixed> */
    private function buildMockDashboard(Empresa $empresa, int $page, int $perPage): array
    {
        $allPatients = $this->recentPatients();
        $totalPatients = \count($allPatients);
        $totalPages = max(1, (int) ceil($totalPatients / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        return [
            'empresa' => $empresa,
            'pos_section' => 'overview',
            'pos_dev_mode' => true,
            'pos_pulse' => $this->buildClinicalPulse(),
            'pos_ticker' => $this->buildTickerSlides(),
            'kpis' => $this->buildKpis(),
            'module_cards' => $this->moduleCards(),
            'recent_patients' => \array_slice($allPatients, $offset, $perPage),
            'patients_pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalPatients,
            ],
            'patients_per_page_options' => self::PATIENTS_PER_PAGE_OPTIONS,
            'active_alerts' => $this->activeAlerts(),
            'timeline_events' => $this->timelineEvents(),
            'team_online' => [],
            'protocol_phases' => $this->protocolPhases(),
        ];
    }

    /** @return array{codigo: string, nome: string, procedimento: string, dia: string, medico: string, ultima_resposta: string, status: string, pri?: string} */
    private function mapPacienteRow(PosOperatorioPaciente $p): array
    {
        $medico = $p->getMedicoResponsavel();
        $ultima = $p->getUltimaResposta();
        $dia = $p->getDiaPosOperatorio();

        $row = [
            'id' => $p->getId(),
            'codigo' => $p->getCodigo(),
            'nome' => PosOperatorioDisplay::pacienteNome($p),
            'procedimento' => $p->getProcedimento() ?? '—',
            'dia' => $dia !== null ? 'D+' . $dia : '—',
            'medico' => $medico
                ? RhProcessDisplay::colaboradorNome($medico->getNome() ?? '', $medico->getEmail())
                : '—',
            'ultima_resposta' => $ultima ? $this->relativeTime($ultima->getRespondidoEm()) : 'pendente',
            'status' => $p->getStatus(),
        ];

        $alertaAberto = $p->getAlertas()->filter(
            static fn (PosOperatorioAlerta $a) => \in_array($a->getStatus(), [
                PosOperatorioAlerta::STATUS_ABERTO,
                PosOperatorioAlerta::STATUS_EM_ATENDIMENTO,
            ], true),
        )->first();

        if ($alertaAberto instanceof PosOperatorioAlerta) {
            $row['pri'] = $alertaAberto->getPrioridade();
        }

        return $row;
    }

    /** @return array{titulo: string, paciente: string, pri: string, tempo: string, tone: string, sla_pct: int|null, detail?: string} */
    private function mapAlertaRow(PosOperatorioAlerta $a): array
    {
        $paciente = $a->getPaciente();
        $tone = match ($a->getPrioridade()) {
            'P1' => 'critical',
            'P2' => 'warn',
            default => 'info',
        };

        return [
            'titulo' => $a->getMotivo(),
            'paciente' => sprintf(
                '%s · %s',
                PosOperatorioDisplay::pacienteNome($paciente),
                $paciente->getCodigo(),
            ),
            'paciente_id' => $paciente->getId(),
            'pri' => $a->getPrioridade(),
            'tempo' => $this->relativeTime($a->getCriadoEm()),
            'tone' => $tone,
            'sla_pct' => $this->slaPercent($a),
            'detail' => 'Fila clínica · ' . $a->getStatus(),
        ];
    }

    private function slaPercent(PosOperatorioAlerta $a): ?int
    {
        $limite = $a->getSlaLimiteEm();
        if (!$limite) {
            return null;
        }
        $total = $limite->getTimestamp() - $a->getCriadoEm()->getTimestamp();
        if ($total <= 0) {
            return 0;
        }
        $elapsed = time() - $a->getCriadoEm()->getTimestamp();

        return min(100, max(0, (int) round(($elapsed / $total) * 100)));
    }

    private function relativeTime(\DateTimeImmutable $dt): string
    {
        $diff = time() - $dt->getTimestamp();
        if ($diff < 60) {
            return 'há ' . $diff . ' seg';
        }
        if ($diff < 3600) {
            return 'há ' . (int) floor($diff / 60) . ' min';
        }
        if ($diff < 86400) {
            return 'há ' . (int) floor($diff / 3600) . 'h';
        }

        return 'ontem';
    }

    /** @return list<array{time: string, label: string, detail: string, icon: string}> */
    private function buildTimelineForEmpresa(Empresa $empresa): array
    {
        $events = $this->eventoRepo->findRecentByEmpresa($empresa, 8);
        $formatted = [];

        foreach ($events as $ev) {
            if (!$ev instanceof PosOperatorioEvento) {
                continue;
            }
            $formatted[] = PosOperatorioTimelineFormatter::format($ev);
        }

        return $formatted;
    }

    /** @param list<PosOperatorioPaciente> $pacientes */
    private function protocolPhasesFromPacientes(array $pacientes): array
    {
        $d0 = $dMid = $dLate = 0;
        foreach ($pacientes as $p) {
            $dia = $p->getDiaPosOperatorio();
            if ($dia === null) {
                continue;
            }
            if ($dia <= 1) {
                ++$d0;
            } elseif ($dia <= 7) {
                ++$dMid;
            } else {
                ++$dLate;
            }
        }

        return [
            ['label' => 'D+0 / D+1', 'count' => $d0, 'tone' => 'accent'],
            ['label' => 'D+2 a D+7', 'count' => $dMid, 'tone' => 'default'],
            ['label' => 'D+8+', 'count' => $dLate, 'tone' => 'muted'],
        ];
    }

    /** @return array{score: int, label: string, tone: string, hint: string} */
    private function buildClinicalPulseFromCounts(int $alertas, int $pendentes): array
    {
        $score = 100 - min(18, $alertas * 5) - min(10, $pendentes * 4);
        $score = min(100, max(40, $score));

        if ($score >= 85) {
            return ['score' => $score, 'label' => 'Acompanhamento estável', 'tone' => 'success', 'hint' => 'Poucas pendências críticas.'];
        }
        if ($score >= 65) {
            return ['score' => $score, 'label' => 'Atenção clínica', 'tone' => 'info', 'hint' => 'Alertas e questionários pedem acompanhamento.'];
        }

        return ['score' => $score, 'label' => 'Priorize alertas', 'tone' => 'warning', 'hint' => 'Ação imediata recomendada.'];
    }

    /** @param list<array<string, mixed>> $pendentes */
    private function buildTickerFromCounts(int $ativos, int $alertas, array $pendentes): array
    {
        return [
            [
                'tag' => 'Alertas',
                'title' => $alertas . ' alerta(s) clínico(s) aberto(s)',
                'text' => $alertas > 0 ? 'Priorize P1 e P2 na fila clínica.' : 'Nenhum alerta aberto no momento.',
                'icon' => 'fa-triangle-exclamation',
                'tone' => $alertas > 0 ? 'amber' : 'blue',
            ],
            [
                'tag' => 'Questionários',
                'title' => \count($pendentes) . ' paciente(s) sem resposta hoje',
                'text' => 'Envie lembretes pelo portal do paciente.',
                'icon' => 'fa-file-medical',
                'tone' => 'blue',
            ],
            [
                'tag' => 'Operação',
                'title' => $ativos . ' paciente(s) em acompanhamento',
                'text' => 'Dados em tempo real da clínica.',
                'icon' => 'fa-user-injured',
                'tone' => 'blue',
            ],
        ];
    }

    /** @return list<array{value: string, label: string, sub?: string, trend?: string, icon?: string}> */
    private function buildKpisFromCounts(int $ativos, int $alertas, int $protocolos): array
    {
        return [
            ['value' => (string) $ativos, 'label' => 'Pacientes ativos', 'sub' => 'Em acompanhamento', 'icon' => 'fa-user-injured'],
            ['value' => (string) $alertas, 'label' => 'Alertas abertos', 'sub' => 'Requerem atenção', 'icon' => 'fa-triangle-exclamation'],
            ['value' => (string) $protocolos, 'label' => 'Protocolos ativos', 'sub' => 'Modelos clínicos', 'icon' => 'fa-clipboard-list'],
            ['value' => '—', 'label' => 'Tempo médio de resposta', 'sub' => 'Fase 2', 'icon' => 'fa-clock'],
        ];
    }

    /** @return list<array{icon: string, title: string, subtitle: string, metric: string}> */
    private function moduleCardsFromCounts(int $ativos, int $protocolos, int $alertas): array
    {
        return [
            ['icon' => 'fa-user-injured', 'title' => 'Pacientes', 'subtitle' => 'Cadastro pós-cirúrgico', 'metric' => $ativos . ' ativos', 'href' => 'app_pos_operatorio_pacientes'],
            ['icon' => 'fa-clipboard-list', 'title' => 'Protocolos', 'subtitle' => 'Checklists por procedimento', 'metric' => $protocolos . ' modelos', 'href' => 'app_pos_operatorio_protocolos'],
            ['icon' => 'fa-file-medical', 'title' => 'Questionários', 'subtitle' => 'Respostas diárias', 'metric' => 'Portal paciente', 'href' => 'app_pos_operatorio_questionarios'],
            ['icon' => 'fa-triangle-exclamation', 'title' => 'Alertas clínicos', 'subtitle' => 'Prioridade P1–P4', 'metric' => $alertas . ' abertos', 'href' => 'app_pos_operatorio_alertas'],
            ['icon' => 'fa-chart-line', 'title' => 'Painel de recuperação', 'subtitle' => 'KPIs e linha do tempo', 'metric' => 'Ao vivo', 'href' => 'app_maturidade'],
            ['icon' => 'fa-mobile-screen', 'title' => 'Portal do paciente', 'subtitle' => 'Acesso mobile', 'metric' => 'Questionários', 'href' => 'app_pos_operatorio_portal'],
        ];
    }

    private function normalizePerPage(int $perPage): int
    {
        return \in_array($perPage, self::PATIENTS_PER_PAGE_OPTIONS, true)
            ? $perPage
            : self::PATIENTS_PER_PAGE_DEFAULT;
    }

    /** @return list<array{value: string, label: string, sub?: string, trend?: string, icon?: string}> */
    private function buildKpis(): array
    {
        return [
            ['value' => '20', 'label' => 'Pacientes ativos', 'sub' => 'Em acompanhamento', 'icon' => 'fa-user-injured'],
            ['value' => '3', 'label' => 'Alertas abertos', 'sub' => 'Requerem atenção', 'trend' => 'down', 'icon' => 'fa-triangle-exclamation'],
            ['value' => '94%', 'label' => 'Questionários respondidos', 'sub' => 'Últimas 24h', 'trend' => 'up', 'icon' => 'fa-file-medical'],
            ['value' => '1h 12m', 'label' => 'Tempo médio de resposta', 'sub' => 'Equipe clínica', 'trend' => 'up', 'icon' => 'fa-clock'],
        ];
    }

    /** @return list<array{icon: string, title: string, subtitle: string, metric: string}> */
    private function moduleCards(): array
    {
        return [
            ['icon' => 'fa-user-injured', 'title' => 'Pacientes', 'subtitle' => 'Cadastro pós-cirúrgico e evolução', 'metric' => '20 ativos', 'href' => 'app_pos_operatorio_pacientes'],
            ['icon' => 'fa-clipboard-list', 'title' => 'Protocolos', 'subtitle' => 'Checklists por tipo de procedimento', 'metric' => '8 modelos', 'href' => 'app_pos_operatorio_protocolos'],
            ['icon' => 'fa-file-medical', 'title' => 'Questionários', 'subtitle' => 'Respostas diárias do paciente', 'metric' => '94% hoje', 'href' => 'app_pos_operatorio_questionarios'],
            ['icon' => 'fa-triangle-exclamation', 'title' => 'Alertas clínicos', 'subtitle' => 'Prioridade P1–P4 e SLA de resposta', 'metric' => '3 abertos', 'href' => 'app_pos_operatorio_alertas'],
            ['icon' => 'fa-chart-line', 'title' => 'Painel médico', 'subtitle' => 'KPIs, CSAT e linha do tempo', 'metric' => 'CSAT 4,8', 'href' => 'app_pos_operatorio'],
            ['icon' => 'fa-mobile-screen', 'title' => 'Portal do paciente', 'subtitle' => 'Acesso mobile ao acompanhamento', 'metric' => '18 acessos hoje', 'href' => 'app_pos_operatorio_portal'],
        ];
    }

    /** @return list<array{codigo: string, nome: string, procedimento: string, dia: string, medico: string, ultima_resposta: string, status: string, pri?: string}> */
    private function recentPatients(): array
    {
        return [
            ['codigo' => 'PO-1042', 'nome' => 'Maria S.', 'procedimento' => 'Artroscopia joelho', 'dia' => 'D+3', 'medico' => 'Dr. Almeida', 'ultima_resposta' => 'há 2h', 'status' => 'ativo'],
            ['codigo' => 'PO-1041', 'nome' => 'João P.', 'procedimento' => 'Apendicectomia', 'dia' => 'D+1', 'medico' => 'Dra. Lima', 'ultima_resposta' => 'há 18 min', 'status' => 'alerta', 'pri' => 'P2'],
            ['codigo' => 'PO-1040', 'nome' => 'Carlos M.', 'procedimento' => 'Herniorrafia inguinal', 'dia' => 'D+2', 'medico' => 'Dr. Almeida', 'ultima_resposta' => 'há 6 min', 'status' => 'alerta', 'pri' => 'P1'],
            ['codigo' => 'PO-1039', 'nome' => 'Ana R.', 'procedimento' => 'Colecistectomia', 'dia' => 'D+7', 'medico' => 'Dra. Costa', 'ultima_resposta' => 'há 45 min', 'status' => 'ativo'],
            ['codigo' => 'PO-1038', 'nome' => 'Pedro L.', 'procedimento' => 'Cesariana eletiva', 'dia' => 'D+5', 'medico' => 'Dra. Lima', 'ultima_resposta' => 'há 1h', 'status' => 'ativo'],
            ['codigo' => 'PO-1037', 'nome' => 'Fernanda K.', 'procedimento' => 'Mamoplastia redução', 'dia' => 'D+4', 'medico' => 'Dr. Souza', 'ultima_resposta' => 'há 3h', 'status' => 'ativo'],
            ['codigo' => 'PO-1036', 'nome' => 'Ricardo T.', 'procedimento' => 'Artroplastia quadril', 'dia' => 'D+10', 'medico' => 'Dr. Almeida', 'ultima_resposta' => 'ontem', 'status' => 'ativo'],
            ['codigo' => 'PO-1035', 'nome' => 'Juliana M.', 'procedimento' => 'Tiroidectomia', 'dia' => 'D+1', 'medico' => 'Dra. Costa', 'ultima_resposta' => 'pendente', 'status' => 'pendente'],
            ['codigo' => 'PO-1034', 'nome' => 'Lucas H.', 'procedimento' => 'Septoplastia', 'dia' => 'D+2', 'medico' => 'Dr. Souza', 'ultima_resposta' => 'há 4h', 'status' => 'ativo'],
            ['codigo' => 'PO-1033', 'nome' => 'Beatriz N.', 'procedimento' => 'Histerectomia', 'dia' => 'D+6', 'medico' => 'Dra. Lima', 'ultima_resposta' => 'há 55 min', 'status' => 'ativo'],
            ['codigo' => 'PO-1032', 'nome' => 'Marcos V.', 'procedimento' => 'Laparoscopia', 'dia' => 'D+3', 'medico' => 'Dr. Almeida', 'ultima_resposta' => 'há 2h', 'status' => 'ativo'],
            ['codigo' => 'PO-1031', 'nome' => 'Camila F.', 'procedimento' => 'Bariátrica', 'dia' => 'D+8', 'medico' => 'Dra. Costa', 'ultima_resposta' => 'ontem', 'status' => 'ativo'],
            ['codigo' => 'PO-1030', 'nome' => 'Roberto G.', 'procedimento' => 'Prótese joelho', 'dia' => 'D+12', 'medico' => 'Dr. Almeida', 'ultima_resposta' => 'há 5h', 'status' => 'ativo'],
            ['codigo' => 'PO-1029', 'nome' => 'Patricia O.', 'procedimento' => 'Mastectomia', 'dia' => 'D+4', 'medico' => 'Dra. Lima', 'ultima_resposta' => 'há 40 min', 'status' => 'ativo'],
            ['codigo' => 'PO-1028', 'nome' => 'Diego A.', 'procedimento' => 'Cirurgia catarata', 'dia' => 'D+1', 'medico' => 'Dr. Souza', 'ultima_resposta' => 'há 25 min', 'status' => 'ativo'],
            ['codigo' => 'PO-1027', 'nome' => 'Helena C.', 'procedimento' => 'Revascularização', 'dia' => 'D+9', 'medico' => 'Dra. Costa', 'ultima_resposta' => 'há 3h', 'status' => 'ativo'],
            ['codigo' => 'PO-1026', 'nome' => 'Gabriel W.', 'procedimento' => 'Uretrotomia', 'dia' => 'D+2', 'medico' => 'Dr. Almeida', 'ultima_resposta' => 'pendente', 'status' => 'pendente'],
            ['codigo' => 'PO-1025', 'nome' => 'Isabela D.', 'procedimento' => 'Cirurgia bariatrica', 'dia' => 'D+5', 'medico' => 'Dra. Lima', 'ultima_resposta' => 'há 1h', 'status' => 'ativo'],
            ['codigo' => 'PO-1024', 'nome' => 'Thiago R.', 'procedimento' => 'Fratura tíbia', 'dia' => 'D+14', 'medico' => 'Dr. Souza', 'ultima_resposta' => 'ontem', 'status' => 'ativo'],
        ];
    }

    /** @return list<array{titulo: string, paciente: string, pri: string, tempo: string, tone: string, sla_pct: int|null, detail?: string}> */
    private function activeAlerts(): array
    {
        return [
            ['titulo' => 'Dor intensa (8/10)', 'paciente' => 'Carlos M. · PO-1040', 'pri' => 'P1', 'tempo' => 'há 6 min', 'tone' => 'critical', 'sla_pct' => 18, 'detail' => 'Escalar para médico plantonista'],
            ['titulo' => 'Febre reportada (38,2°C)', 'paciente' => 'João P. · PO-1041', 'pri' => 'P2', 'tempo' => 'há 18 min', 'tone' => 'warn', 'sla_pct' => 42, 'detail' => 'Questionário D+1'],
            ['titulo' => 'Questionário não respondido', 'paciente' => 'Juliana M. · PO-1035', 'pri' => 'P3', 'tempo' => 'há 4h', 'tone' => 'info', 'sla_pct' => null, 'detail' => 'Lembrete automático'],
        ];
    }

    /** @return list<array{time: string, label: string, detail: string, icon: string}> */
    private function timelineEvents(): array
    {
        return [
            ['time' => '17:04', 'label' => 'Alerta P1 aberto', 'detail' => 'Carlos M. — dor intensa', 'icon' => 'fa-triangle-exclamation'],
            ['time' => '16:48', 'label' => 'Questionário respondido', 'detail' => 'Ana R. · PO-1039', 'icon' => 'fa-file-medical'],
            ['time' => '16:12', 'label' => 'Alta registrada', 'detail' => 'Maria S. · PO-1042', 'icon' => 'fa-user-check'],
        ];
    }

    /** @return list<array{label: string, count: int, tone: string}> */
    private function protocolPhases(): array
    {
        return [
            ['label' => 'D+0 / D+1', 'count' => 6, 'tone' => 'accent'],
            ['label' => 'D+2 a D+7', 'count' => 11, 'tone' => 'default'],
            ['label' => 'D+8+', 'count' => 7, 'tone' => 'muted'],
        ];
    }

    /** @return array{score: int, label: string, tone: string, hint: string} */
    private function buildClinicalPulse(): array
    {
        return ['score' => 88, 'label' => 'Acompanhamento estável', 'tone' => 'success', 'hint' => 'Preview ilustrativo — execute app:pos-operatorio:seed para dados reais.'];
    }

    /** @return list<array{tag: string, title: string, text: string, icon: string, tone: string, route_label?: string}> */
    private function buildTickerSlides(): array
    {
        return [
            ['tag' => 'Preview', 'title' => 'Modo demonstração', 'text' => 'Rode php bin/console app:pos-operatorio:seed para popular dados clínicos.', 'icon' => 'fa-flask', 'tone' => 'blue'],
            ['tag' => 'Clínica', 'title' => 'Acompanhamento pós-cirúrgico', 'text' => 'Cadastre pacientes, protocolos e questionários diários.', 'icon' => 'fa-user-injured', 'tone' => 'blue'],
        ];
    }
}
