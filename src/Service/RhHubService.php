<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\RhFerias;
use App\Entity\RhFolhaCompetencia;
use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Repository\FuncionarioRepository;
use App\Repository\RhFeriasRepository;
use App\Repository\RhFolhaCompetenciaRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;

class RhHubService
{
    public function __construct(
        private FuncionarioRepository $funcionarioRepo,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private RhOnboardingService $onboarding,
        private RhOffboardingService $offboarding,
        private RhFeriasRepository $feriasRepo,
        private RhFolhaCompetenciaRepository $folhaRepo,
        private RhFeriasService $feriasService,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(Empresa $empresa): array
    {
        $this->feriasService->syncStatusByDate($empresa);

        $now = new \DateTimeImmutable();
        $ref = $now->format('Y-m');
        $monthStart = $now->modify('first day of this month')->setTime(0, 0);
        $folha = $this->folhaRepo->findOneByReferencia($empresa, $ref);
        $headcount = $this->funcionarioRepo->countByStatusGrouped($empresa);
        $totalFuncionarios = array_sum($headcount);
        $ativos = $headcount['ATIVO'] ?? 0;
        $onboardingOpen = $this->onboarding->countOpen($empresa);
        $offboardingOpen = $this->offboarding->countOpen($empresa);
        $feriasPendentes = $this->feriasRepo->countByStatus($empresa, RhFerias::STATUS_SOLICITADA);
        $feriasEmGozo = $this->feriasRepo->countByStatus($empresa, RhFerias::STATUS_EM_GOZO);
        $emFeriasStatus = $headcount['FERIAS'] ?? 0;

        $openOnboarding = $this->onboardingRepo->findOpenRecent($empresa, 8);
        $openOffboarding = $this->offboardingRepo->findOpenRecent($empresa, 6);
        $pendingFerias = $this->feriasRepo->findPendingRecent($empresa, 8);
        $feriasGozo = $this->feriasRepo->findEmGozo($empresa, 6);
        $upcomingStarts = $this->onboardingRepo->findUpcomingStarts($empresa, 30, 6);
        $feriasStarting = $this->feriasRepo->findStartingSoon($empresa, 21, 6);
        $feriasReturns = $this->feriasRepo->findReturnsSoon($empresa, 21, 6);

        $avgChecklist = $this->onboardingRepo->averageOpenChecklistProgress($empresa);
        $admitidosMes = $this->funcionarioRepo->countAdmittedSince($empresa, $monthStart);
        $concluidosMes = $this->onboardingRepo->countConcludedSince($empresa, $monthStart);
        $semSalario = $this->funcionarioRepo->countActiveWithoutSalary($empresa);
        $comUsuario = $this->funcionarioRepo->countWithPlatformUser($empresa);

        $pulse = $this->computePulseScore(
            $feriasPendentes,
            $onboardingOpen,
            $offboardingOpen,
            $folha,
            $openOnboarding,
            $semSalario,
        );

        $processBoard = $this->buildProcessBoard($openOnboarding, $openOffboarding);
        $agenda = $this->buildAgenda($upcomingStarts, $feriasStarting, $feriasReturns, $folha, $ref, $now);
        $activityStream = $this->buildActivityStream(
            $openOnboarding,
            $openOffboarding,
            $pendingFerias,
            $feriasGozo,
            $this->funcionarioRepo->findRecentByEmpresa($empresa, 5),
            $now,
        );

        return [
            'total_funcionarios' => $this->funcionarioRepo->countByEmpresa($empresa),
            'ativos' => $ativos,
            'onboarding_open' => $onboardingOpen,
            'offboarding_open' => $offboardingOpen,
            'ferias_em_gozo' => $feriasEmGozo,
            'ferias_pendentes' => $feriasPendentes,
            'folha_mes' => $folha,
            'folha_status' => $folha ? ($folha->isFechada() ? 'Fechada' : 'Aberta') : 'Não gerada',
            'folha_ref' => $ref,
            'folha_ref_label' => $this->formatCompetencia($ref),
            'headcount' => $headcount,
            'headcount_total' => $totalFuncionarios,
            'rh_pulse' => $pulse,
            'lifecycle_stages' => $this->buildLifecycleStages(
                $onboardingOpen,
                $ativos,
                max($feriasEmGozo, $emFeriasStatus),
                $offboardingOpen,
            ),
            'headcount_segments' => $this->buildHeadcountSegments($headcount, $totalFuncionarios),
            'focus_queue' => $this->buildFocusQueue($pendingFerias, $openOnboarding, $openOffboarding, $folha, $ref),
            'open_onboarding' => $openOnboarding,
            'open_offboarding' => $openOffboarding,
            'pending_ferias' => $pendingFerias,
            'ferias_em_gozo_list' => $feriasGozo,
            'upcoming_starts' => $upcomingStarts,
            'recent_hires' => $this->funcionarioRepo->findRecentByEmpresa($empresa, 5),
            'hub_insights' => $this->buildInsights(
                $ativos,
                $totalFuncionarios,
                $onboardingOpen,
                $offboardingOpen,
                $feriasPendentes,
                $feriasEmGozo,
                $avgChecklist,
                $admitidosMes,
                $concluidosMes,
                $semSalario,
                $comUsuario,
                $folha,
            ),
            'folha_snapshot' => $this->buildFolhaSnapshot($folha, $ref),
            'process_board' => $processBoard,
            'agenda_events' => $agenda,
            'activity_stream' => $activityStream,
            'processos_abertos_total' => $onboardingOpen + $offboardingOpen + $feriasPendentes,
        ];
    }

    /**
     * @param list<RhOnboardingProcess> $openOnboarding
     *
     * @return array{score: int, label: string, tone: string, hint: string, factors: list<array>}
     */
    private function computePulseScore(
        int $feriasPendentes,
        int $onboardingOpen,
        int $offboardingOpen,
        ?RhFolhaCompetencia $folha,
        array $openOnboarding,
        int $semSalario,
    ): array {
        $score = 100;
        $factors = [];

        if ($feriasPendentes > 0) {
            $impact = min(20, $feriasPendentes * 6);
            $score -= $impact;
            $factors[] = [
                'icon' => 'fa-umbrella-beach',
                'label' => 'Férias aguardando',
                'detail' => $feriasPendentes . ' solicitação(ões)',
                'impact' => -$impact,
                'tone' => 'amber',
            ];
        }

        if ($offboardingOpen > 0) {
            $impact = min(12, $offboardingOpen * 4);
            $score -= $impact;
            $factors[] = [
                'icon' => 'fa-door-open',
                'label' => 'Offboarding ativo',
                'detail' => $offboardingOpen . ' processo(s)',
                'impact' => -$impact,
                'tone' => 'rose',
            ];
        }

        $stalled = 0;
        foreach ($openOnboarding as $p) {
            if ($p->checklistProgress() < 40) {
                $score -= 4;
                ++$stalled;
            }
        }
        if ($stalled > 0) {
            $impact = min(16, $stalled * 4);
            $factors[] = [
                'icon' => 'fa-clipboard-list',
                'label' => 'Onboarding lento',
                'detail' => $stalled . ' abaixo de 40%',
                'impact' => -$impact,
                'tone' => 'warning',
            ];
        }

        if (!$folha) {
            $score -= 10;
            $factors[] = [
                'icon' => 'fa-file-invoice-dollar',
                'label' => 'Folha do mês',
                'detail' => 'Ainda não gerada',
                'impact' => -10,
                'tone' => 'slate',
            ];
        } elseif ($folha->isFechada()) {
            $score += 5;
            $factors[] = [
                'icon' => 'fa-circle-check',
                'label' => 'Folha fechada',
                'detail' => 'Competência em dia',
                'impact' => 5,
                'tone' => 'success',
            ];
        }

        if ($semSalario > 0) {
            $impact = min(8, $semSalario * 2);
            $score -= $impact;
            $factors[] = [
                'icon' => 'fa-coins',
                'label' => 'Salário pendente',
                'detail' => $semSalario . ' ativo(s) sem valor',
                'impact' => -$impact,
                'tone' => 'warning',
            ];
        }

        if ($factors === []) {
            $factors[] = [
                'icon' => 'fa-heart-pulse',
                'label' => 'Operação saudável',
                'detail' => 'Sem alertas críticos',
                'impact' => 0,
                'tone' => 'success',
            ];
        }

        $score = min(100, max(35, $score));

        if ($score >= 85) {
            return [
                'score' => $score,
                'label' => 'Equipe estável',
                'tone' => 'success',
                'hint' => 'Poucas pendências críticas no ciclo de pessoas.',
                'factors' => $factors,
            ];
        }
        if ($score >= 65) {
            return [
                'score' => $score,
                'label' => 'Atenção moderada',
                'tone' => 'info',
                'hint' => 'Alguns processos pedem acompanhamento esta semana.',
                'factors' => $factors,
            ];
        }

        return [
            'score' => $score,
            'label' => 'Priorize o RH',
            'tone' => 'warning',
            'hint' => 'Há solicitações ou admissões que precisam de ação.',
            'factors' => $factors,
        ];
    }

    /** @return list<array> */
    private function buildInsights(
        int $ativos,
        int $total,
        int $onboardingOpen,
        int $offboardingOpen,
        int $feriasPendentes,
        int $feriasEmGozo,
        int $avgChecklist,
        int $admitidosMes,
        int $concluidosMes,
        int $semSalario,
        int $comUsuario,
        ?RhFolhaCompetencia $folha,
    ): array {
        $taxaAtivos = $total > 0 ? round(($ativos / $total) * 100) : 0;

        return [
            [
                'icon' => 'fa-chart-pie',
                'label' => 'Taxa de ativos',
                'value' => $taxaAtivos . '%',
                'sub' => $ativos . ' de ' . $total . ' no cadastro',
                'tone' => 'blue',
            ],
            [
                'icon' => 'fa-list-check',
                'label' => 'Checklist médio',
                'value' => $avgChecklist . '%',
                'sub' => $onboardingOpen > 0 ? 'Admissões em aberto' : 'Sem admissões abertas',
                'tone' => 'teal',
            ],
            [
                'icon' => 'fa-user-check',
                'label' => 'Admissões no mês',
                'value' => (string) $concluidosMes,
                'sub' => $admitidosMes . ' novos cadastros',
                'tone' => 'green',
            ],
            [
                'icon' => 'fa-briefcase',
                'label' => 'Processos abertos',
                'value' => (string) ($onboardingOpen + $offboardingOpen),
                'sub' => $feriasPendentes . ' férias pendentes',
                'tone' => 'amber',
            ],
            [
                'icon' => 'fa-key',
                'label' => 'Com acesso',
                'value' => (string) $comUsuario,
                'sub' => 'Usuários na plataforma',
                'tone' => 'violet',
            ],
            [
                'icon' => 'fa-umbrella-beach',
                'label' => 'Em férias',
                'value' => (string) $feriasEmGozo,
                'sub' => $folha ? 'Folha ' . strtolower($folha->isFechada() ? 'fechada' : 'aberta') : 'Folha não gerada',
                'tone' => 'amber',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function buildFolhaSnapshot(?RhFolhaCompetencia $folha, string $ref): array
    {
        if (!$folha) {
            return [
                'has_folha' => false,
                'referencia_label' => $this->formatCompetencia($ref),
                'status' => 'Não gerada',
                'status_tone' => 'warning',
                'liquido' => null,
                'proventos' => null,
                'descontos' => null,
                'lancamentos' => 0,
                'route' => 'app_rh_folha',
                'route_params' => [],
            ];
        }

        return [
            'has_folha' => true,
            'referencia_label' => $folha->getReferenciaLabel(),
            'status' => $folha->isFechada() ? 'Fechada' : 'Aberta',
            'status_tone' => $folha->isFechada() ? 'success' : 'info',
            'liquido' => (float) $folha->getTotalLiquido(),
            'proventos' => (float) $folha->getTotalProventos(),
            'descontos' => (float) $folha->getTotalDescontos(),
            'lancamentos' => $folha->getLancamentos()->count(),
            'route' => 'app_rh_folha_show',
            'route_params' => ['id' => $folha->getId()],
        ];
    }

    /**
     * @param list<RhOnboardingProcess>  $openOnboarding
     * @param list<RhOffboardingProcess> $openOffboarding
     *
     * @return list<array>
     */
    private function buildProcessBoard(array $openOnboarding, array $openOffboarding): array
    {
        $board = [];
        foreach ($openOnboarding as $p) {
            $pending = [];
            foreach ($p->getChecklist() as $item) {
                if (empty($item['done'])) {
                    $pending[] = $item['label'] ?? $item['id'] ?? 'Item';
                }
            }
            $board[] = [
                'type' => 'onboarding',
                'title' => $p->getNome(),
                'subtitle' => $p->getCargo() ?: $p->getEmail(),
                'progress' => $p->checklistProgress(),
                'meta' => $p->getDataPrevista() ? 'Início ' . $p->getDataPrevista()->format('d/m/Y') : 'Sem data prevista',
                'pending_items' => \array_slice($pending, 0, 2),
                'route' => 'app_rh_admissoes_show',
                'route_params' => ['id' => $p->getId()],
                'tone' => 'teal',
            ];
        }
        foreach ($openOffboarding as $p) {
            $board[] = [
                'type' => 'offboarding',
                'title' => $p->getFuncionario()->getNome(),
                'subtitle' => $p->getMotivo() ?: 'Desligamento',
                'progress' => $p->checklistProgress(),
                'meta' => $p->getDataPrevista() ? 'Previsto ' . $p->getDataPrevista()->format('d/m/Y') : 'Em andamento',
                'pending_items' => [],
                'route' => 'app_rh_demissoes_show',
                'route_params' => ['id' => $p->getId()],
                'tone' => 'rose',
            ];
        }

        return $board;
    }

    /** @return list<array> */
    private function buildAgenda(
        array $upcomingStarts,
        array $feriasStarting,
        array $feriasReturns,
        ?RhFolhaCompetencia $folha,
        string $ref,
        \DateTimeImmutable $now,
    ): array {
        $events = [];

        foreach ($upcomingStarts as $p) {
            if (!$p->getDataPrevista()) {
                continue;
            }
            $events[] = [
                'date' => $p->getDataPrevista(),
                'icon' => 'fa-user-plus',
                'tone' => 'teal',
                'title' => 'Início — ' . $p->getNome(),
                'subtitle' => $p->getCargo() ?: 'Nova admissão',
                'route' => 'app_rh_admissoes_show',
                'route_params' => ['id' => $p->getId()],
            ];
        }

        foreach ($feriasStarting as $f) {
            $events[] = [
                'date' => $f->getDataInicio(),
                'icon' => 'fa-plane-departure',
                'tone' => 'amber',
                'title' => 'Férias iniciam — ' . $f->getFuncionario()->getNome(),
                'subtitle' => $f->getDias() . ' dias',
                'route' => 'app_rh_ferias_show',
                'route_params' => ['id' => $f->getId()],
            ];
        }

        foreach ($feriasReturns as $f) {
            $events[] = [
                'date' => $f->getDataFim(),
                'icon' => 'fa-plane-arrival',
                'tone' => 'green',
                'title' => 'Retorno — ' . $f->getFuncionario()->getNome(),
                'subtitle' => 'Fim das férias',
                'route' => 'app_rh_ferias_show',
                'route_params' => ['id' => $f->getId()],
            ];
        }

        if (!$folha) {
            $deadline = $now->modify('last day of this month');
            $events[] = [
                'date' => $deadline,
                'icon' => 'fa-file-invoice-dollar',
                'tone' => 'violet',
                'title' => 'Gerar folha — ' . $this->formatCompetencia($ref),
                'subtitle' => 'Competência ainda não criada',
                'route' => 'app_rh_folha',
                'route_params' => [],
            ];
        }

        usort($events, static fn (array $a, array $b) => $a['date'] <=> $b['date']);

        $out = [];
        foreach (\array_slice($events, 0, 8) as $e) {
            $out[] = array_merge($e, [
                'date_label' => $e['date']->format('d/m'),
                'weekday' => $this->weekdayShort($e['date']),
                'is_today' => $e['date']->format('Y-m-d') === $now->format('Y-m-d'),
            ]);
        }

        return $out;
    }

    /**
     * @param list<RhOnboardingProcess>  $openOnboarding
     * @param list<RhOffboardingProcess> $openOffboarding
     * @param list<RhFerias>             $pendingFerias
     * @param list<RhFerias>             $feriasGozo
     * @param list<\App\Entity\Funcionario> $recentHires
     *
     * @return list<array>
     */
    private function buildActivityStream(
        array $openOnboarding,
        array $openOffboarding,
        array $pendingFerias,
        array $feriasGozo,
        array $recentHires,
        \DateTimeImmutable $now,
    ): array {
        $events = [];

        foreach ($openOnboarding as $p) {
            $events[] = [
                'sort_at' => $p->getAtualizadoEm(),
                'icon' => 'fa-user-plus',
                'tone' => 'teal',
                'title' => 'Onboarding: ' . $p->getNome(),
                'subtitle' => 'Checklist ' . $p->checklistProgress() . '%',
                'route' => 'app_rh_admissoes_show',
                'route_params' => ['id' => $p->getId()],
            ];
        }
        foreach ($openOffboarding as $p) {
            $events[] = [
                'sort_at' => $p->getAtualizadoEm(),
                'icon' => 'fa-user-minus',
                'tone' => 'rose',
                'title' => 'Offboarding: ' . $p->getFuncionario()->getNome(),
                'subtitle' => $p->checklistProgress() . '% do checklist',
                'route' => 'app_rh_demissoes_show',
                'route_params' => ['id' => $p->getId()],
            ];
        }
        foreach ($pendingFerias as $f) {
            $events[] = [
                'sort_at' => $f->getCriadoEm(),
                'icon' => 'fa-hourglass-half',
                'tone' => 'amber',
                'title' => 'Férias solicitadas',
                'subtitle' => $f->getFuncionario()->getNome(),
                'route' => 'app_rh_ferias_show',
                'route_params' => ['id' => $f->getId()],
            ];
        }
        foreach ($feriasGozo as $f) {
            $events[] = [
                'sort_at' => $f->getDataInicio(),
                'icon' => 'fa-umbrella-beach',
                'tone' => 'green',
                'title' => 'Em férias',
                'subtitle' => $f->getFuncionario()->getNome(),
                'route' => 'app_rh_ferias_show',
                'route_params' => ['id' => $f->getId()],
            ];
        }
        foreach ($recentHires as $f) {
            $events[] = [
                'sort_at' => $f->getCriadoEm(),
                'icon' => 'fa-id-badge',
                'tone' => 'blue',
                'title' => 'Cadastro: ' . $f->getNome(),
                'subtitle' => $f->getCargo() ?: 'Novo colaborador',
                'route' => 'app_rh_funcionario_show',
                'route_params' => ['id' => $f->getId()],
            ];
        }

        usort($events, static fn (array $a, array $b) => $b['sort_at'] <=> $a['sort_at']);

        $out = [];
        foreach (\array_slice($events, 0, 10) as $e) {
            $sortAt = $e['sort_at'];
            unset($e['sort_at']);
            $out[] = array_merge($e, [
                'time_label' => $this->relativeTimeLabel($sortAt, $now),
            ]);
        }

        return $out;
    }

    private function relativeTimeLabel(\DateTimeImmutable $at, \DateTimeImmutable $now): string
    {
        $diff = $now->getTimestamp() - $at->getTimestamp();
        if ($diff < 3600) {
            return 'Agora há pouco';
        }
        if ($diff < 86400) {
            return 'Hoje';
        }
        if ($diff < 172800) {
            return 'Ontem';
        }

        return $at->format('d/m/Y');
    }

    private function weekdayShort(\DateTimeImmutable $date): string
    {
        $days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        return $days[(int) $date->format('w')];
    }

    /** @return list<array> */
    private function buildLifecycleStages(int $entrada, int $ativos, int $ferias, int $saida): array
    {
        return [
            ['key' => 'entrada', 'label' => 'Entrada', 'count' => $entrada, 'icon' => 'fa-user-plus', 'tone' => 'blue', 'route' => 'app_rh_admissoes'],
            ['key' => 'ativos', 'label' => 'Ativos', 'count' => $ativos, 'icon' => 'fa-users', 'tone' => 'green', 'route' => 'app_rh_funcionarios'],
            ['key' => 'ferias', 'label' => 'Em férias', 'count' => $ferias, 'icon' => 'fa-umbrella-beach', 'tone' => 'amber', 'route' => 'app_rh_ferias'],
            ['key' => 'saida', 'label' => 'Saída', 'count' => $saida, 'icon' => 'fa-user-minus', 'tone' => 'rose', 'route' => 'app_rh_demissoes'],
        ];
    }

    /** @param array<string, int> $headcount @return list<array> */
    private function buildHeadcountSegments(array $headcount, int $total): array
    {
        $map = [
            'ATIVO' => ['label' => 'Ativos', 'tone' => 'green'],
            'FERIAS' => ['label' => 'Férias', 'tone' => 'amber'],
            'AFASTADO' => ['label' => 'Afastados', 'tone' => 'slate'],
            'INATIVO' => ['label' => 'Inativos', 'tone' => 'muted'],
        ];
        $segments = [];
        foreach ($map as $status => $meta) {
            $count = $headcount[$status] ?? 0;
            if ($count === 0 && $status === 'AFASTADO') {
                continue;
            }
            $segments[] = [
                'status' => $status,
                'label' => $meta['label'],
                'count' => $count,
                'pct' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
                'tone' => $meta['tone'],
            ];
        }

        return $segments;
    }

    /** @return list<array> */
    private function buildFocusQueue(
        array $pendingFerias,
        array $openOnboarding,
        array $openOffboarding,
        ?RhFolhaCompetencia $folha,
        string $ref,
    ): array {
        $items = [];

        foreach ($pendingFerias as $f) {
            $items[] = [
                'urgency' => 'high',
                'icon' => 'fa-umbrella-beach',
                'title' => 'Aprovar férias',
                'subtitle' => $f->getFuncionario()->getNome(),
                'route' => 'app_rh_ferias_show',
                'route_params' => ['id' => $f->getId()],
                'meta' => $f->getDataInicio()->format('d/m') . ' – ' . $f->getDataFim()->format('d/m'),
            ];
        }

        foreach ($openOnboarding as $p) {
            if ($p->checklistProgress() >= 100) {
                $items[] = [
                    'urgency' => 'high',
                    'icon' => 'fa-user-check',
                    'title' => 'Concluir admissão',
                    'subtitle' => $p->getNome(),
                    'route' => 'app_rh_admissoes_show',
                    'route_params' => ['id' => $p->getId()],
                    'meta' => 'Checklist completo',
                    'progress' => 100,
                ];
            } elseif ($p->checklistProgress() < 70) {
                $items[] = [
                    'urgency' => 'medium',
                    'icon' => 'fa-clipboard-list',
                    'title' => 'Onboarding em andamento',
                    'subtitle' => $p->getNome(),
                    'route' => 'app_rh_admissoes_show',
                    'route_params' => ['id' => $p->getId()],
                    'meta' => $p->getCargo() ?: 'Sem cargo',
                    'progress' => $p->checklistProgress(),
                ];
            }
        }

        foreach ($openOffboarding as $p) {
            $items[] = [
                'urgency' => 'medium',
                'icon' => 'fa-door-open',
                'title' => 'Offboarding aberto',
                'subtitle' => $p->getFuncionario()->getNome(),
                'route' => 'app_rh_demissoes_show',
                'route_params' => ['id' => $p->getId()],
                'meta' => $p->getMotivo() ?: 'Em andamento',
                'progress' => $p->checklistProgress(),
            ];
        }

        if (!$folha) {
            $items[] = [
                'urgency' => 'low',
                'icon' => 'fa-file-invoice-dollar',
                'title' => 'Gerar folha do mês',
                'subtitle' => $this->formatCompetencia($ref),
                'route' => 'app_rh_folha',
                'route_params' => [],
                'meta' => 'Competência não gerada',
            ];
        } elseif (!$folha->isFechada()) {
            $items[] = [
                'urgency' => 'low',
                'icon' => 'fa-lock-open',
                'title' => 'Folha aberta',
                'subtitle' => $this->formatCompetencia($ref),
                'route' => 'app_rh_folha_show',
                'route_params' => ['id' => $folha->getId()],
                'meta' => 'Revisar antes de fechar',
            ];
        }

        $order = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($items, static fn (array $a, array $b) => ($order[$a['urgency']] ?? 9) <=> ($order[$b['urgency']] ?? 9));

        return \array_slice($items, 0, 8);
    }

    private function formatCompetencia(string $ref): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $ref, $m)) {
            return $ref;
        }
        $months = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
            '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
            '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro',
        ];

        return ($months[$m[2]] ?? $m[2]) . ' ' . $m[1];
    }
}
