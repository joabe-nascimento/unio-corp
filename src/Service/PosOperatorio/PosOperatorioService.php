<?php

namespace App\Service\PosOperatorio;

use App\Entity\User;
use App\Service\WorkspaceService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Hub Pós-Operatório — dashboard e dados ilustrativos (MVP em desenvolvimento).
 */
final class PosOperatorioService
{
    public const PATIENTS_PER_PAGE_DEFAULT = 10;

    /** @var list<int> */
    public const PATIENTS_PER_PAGE_OPTIONS = [10, 15];

    public function __construct(
        private WorkspaceService $workspace,
    ) {}

    /** @return array<string, mixed> */
    public function getDashboard(User $user, int $page = 1, int $perPage = self::PATIENTS_PER_PAGE_DEFAULT): array
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw new BadRequestHttpException('Selecione um workspace para acessar o Núcleo Pós-Operatório.');
        }

        $perPage = $this->normalizePerPage($perPage);
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
            'team_online' => $this->teamOnline(),
            'protocol_phases' => $this->protocolPhases(),
            'vitoria_insight' => $this->vitoriaInsight(),
        ];
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
            ['icon' => 'fa-user-injured', 'title' => 'Pacientes', 'subtitle' => 'Cadastro pós-cirúrgico e evolução', 'metric' => '20 ativos'],
            ['icon' => 'fa-clipboard-list', 'title' => 'Protocolos', 'subtitle' => 'Checklists por tipo de procedimento', 'metric' => '8 modelos'],
            ['icon' => 'fa-file-medical', 'title' => 'Questionários', 'subtitle' => 'Respostas diárias do paciente', 'metric' => '94% hoje'],
            ['icon' => 'fa-triangle-exclamation', 'title' => 'Alertas clínicos', 'subtitle' => 'Prioridade P1–P4 e SLA de resposta', 'metric' => '3 abertos'],
            ['icon' => 'fa-chart-line', 'title' => 'Painel médico', 'subtitle' => 'KPIs, CSAT e linha do tempo', 'metric' => 'CSAT 4,8'],
            ['icon' => 'fa-mobile-screen', 'title' => 'Portal do paciente', 'subtitle' => 'Acesso mobile ao acompanhamento', 'metric' => '18 acessos hoje'],
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
            [
                'titulo' => 'Dor intensa (8/10)',
                'paciente' => 'Carlos M. · PO-1040',
                'pri' => 'P1',
                'tempo' => 'há 6 min',
                'tone' => 'critical',
                'sla_pct' => 18,
                'detail' => 'Escalar para médico plantonista',
            ],
            [
                'titulo' => 'Febre reportada (38,2°C)',
                'paciente' => 'João P. · PO-1041',
                'pri' => 'P2',
                'tempo' => 'há 18 min',
                'tone' => 'warn',
                'sla_pct' => 42,
                'detail' => 'Questionário D+1 · protocolo apendicectomia',
            ],
            [
                'titulo' => 'Questionário não respondido',
                'paciente' => 'Juliana M. · PO-1035',
                'pri' => 'P3',
                'tempo' => 'há 4h',
                'tone' => 'info',
                'sla_pct' => null,
                'detail' => 'Lembrete automático às 20h',
            ],
            [
                'titulo' => 'Sangramento leve no curativo',
                'paciente' => 'Pedro L. · PO-1038',
                'pri' => 'P3',
                'tempo' => 'há 52 min',
                'tone' => 'warn',
                'sla_pct' => 65,
                'detail' => 'Enfermagem solicitou foto',
            ],
            [
                'titulo' => 'Náusea persistente',
                'paciente' => 'Fernanda K. · PO-1037',
                'pri' => 'P4',
                'tempo' => 'há 2h',
                'tone' => 'info',
                'sla_pct' => null,
                'detail' => 'Orientação Vitória enviada',
            ],
        ];
    }

    /** @return list<array{time: string, label: string, detail: string, icon: string}> */
    private function timelineEvents(): array
    {
        return [
            ['time' => '17:04', 'label' => 'Alerta P1 aberto', 'detail' => 'Carlos M. — dor intensa reportada', 'icon' => 'fa-triangle-exclamation'],
            ['time' => '16:48', 'label' => 'Questionário respondido', 'detail' => 'Ana R. · PO-1039 · D+7', 'icon' => 'fa-file-medical'],
            ['time' => '16:12', 'label' => 'Alta pós-operatória registrada', 'detail' => 'Maria S. · PO-1042 · artroscopia', 'icon' => 'fa-user-check'],
            ['time' => '15:30', 'label' => 'Lembrete enviado', 'detail' => '2 pacientes D+1 sem resposta', 'icon' => 'fa-bell'],
            ['time' => '14:05', 'label' => 'Protocolo atualizado', 'detail' => 'Apendicectomia — checklist D+3', 'icon' => 'fa-clipboard-list'],
        ];
    }

    /** @return list<array{initials: string, nome: string, role: string, status: string}> */
    private function teamOnline(): array
    {
        return [
            ['initials' => 'DL', 'nome' => 'Dra. Lima', 'role' => 'Cirurgia geral', 'status' => 'online'],
            ['initials' => 'RA', 'nome' => 'Dr. Almeida', 'role' => 'Ortopedia', 'status' => 'online'],
            ['initials' => 'EN', 'nome' => 'Enf. Paula', 'role' => 'Enfermagem', 'status' => 'online'],
            ['initials' => 'MC', 'nome' => 'Dra. Costa', 'role' => 'Endocrino', 'status' => 'ausente'],
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

    /** @return array{text: string, action: string, bullets: list<string>} */
    private function vitoriaInsight(): array
    {
        return [
            'text' => 'Identifiquei 2 pacientes em D+1 sem questionário respondido e 1 alerta P1 próximo do estouro de SLA.',
            'action' => 'Ver plano sugerido',
            'bullets' => [
                'Enviar lembrete para Juliana M. (PO-1035) antes das 20h',
                'Priorizar contato com Carlos M. (PO-1040) — dor 8/10',
                '94% de adesão nas últimas 24h — acima da meta de 90%',
            ],
        ];
    }

    private function normalizePerPage(int $perPage): int
    {
        return \in_array($perPage, self::PATIENTS_PER_PAGE_OPTIONS, true)
            ? $perPage
            : self::PATIENTS_PER_PAGE_DEFAULT;
    }

    /** @return array{score: int, label: string, tone: string, hint: string} */
    private function buildClinicalPulse(): array
    {
        $alerts = 3;
        $pendingResponses = 2;
        $responseRate = 94;

        $score = 100;
        $score -= min(18, $alerts * 5);
        $score -= min(10, $pendingResponses * 4);
        if ($responseRate >= 90) {
            $score += 4;
        }
        $score = min(100, max(40, $score));

        if ($score >= 85) {
            return [
                'score' => $score,
                'label' => 'Acompanhamento estável',
                'tone' => 'success',
                'hint' => 'Poucas pendências críticas no pós-operatório.',
            ];
        }
        if ($score >= 65) {
            return [
                'score' => $score,
                'label' => 'Atenção clínica',
                'tone' => 'info',
                'hint' => 'Alguns alertas e questionários pedem acompanhamento hoje.',
            ];
        }

        return [
            'score' => $score,
            'label' => 'Priorize alertas',
            'tone' => 'warning',
            'hint' => 'Há pacientes e SLAs que precisam de ação imediata.',
        ];
    }

    /**
     * @return list<array{tag: string, title: string, text: string, icon: string, tone: string, route_label?: string}>
     */
    private function buildTickerSlides(): array
    {
        return [
            [
                'tag' => 'Alertas',
                'title' => '3 alertas clínicos abertos',
                'text' => 'Priorize P1 e P2 — Carlos M. (PO-1040) está próximo do estouro de SLA.',
                'icon' => 'fa-triangle-exclamation',
                'tone' => 'amber',
                'route_label' => 'Ver alertas',
            ],
            [
                'tag' => 'Questionários',
                'title' => '2 pacientes sem resposta hoje',
                'text' => 'Envie lembrete para Juliana M. e Gabriel W. antes do fechamento do plantão.',
                'icon' => 'fa-file-medical',
                'tone' => 'blue',
                'route_label' => 'Ver pendências',
            ],
            [
                'tag' => 'Operação',
                'title' => '20 pacientes em acompanhamento ativo',
                'text' => '94% de adesão nas últimas 24h — acima da meta de 90% do núcleo.',
                'icon' => 'fa-user-injured',
                'tone' => 'blue',
                'route_label' => 'Ver pacientes',
            ],
        ];
    }
}
