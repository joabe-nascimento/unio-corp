<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\RhFerias;
use App\Entity\RhFolhaCompetencia;
use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Repository\FuncionarioRepository;
use App\Repository\RhFeriasRepository;
use App\Repository\RhEsocialLoteRepository;
use App\Repository\RhFolhaCompetenciaRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Service\Rh\RhModuleStatsService;

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
        private RhModuleStatsService $moduleStats,
        private RhEsocialLoteRepository $esocialRepo,
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
            'hub_modules' => $this->moduleStats->hubModules($empresa),
            'rh_ticker' => $this->tickerSlides($empresa),
        ];
    }

    /**
     * @return list<array{tag: string, title: string, text: string, icon: string, tone: string, route?: string, route_label?: string, route_params?: array<string, int|string>}>
     */
    public function tickerSlides(Empresa $empresa): array
    {
        $ctx = $this->collectTickerContext($empresa);

        return $this->buildTickerSlides(
            $ctx['ativos'],
            $ctx['onboarding_open'],
            $ctx['offboarding_open'],
            $ctx['ferias_pendentes'],
            $ctx['ferias_em_gozo'],
            $ctx['sem_salario'],
            $ctx['com_usuario'],
            $ctx['admitidos_mes'],
            $ctx['concluidos_mes'],
            $ctx['folha'],
            $ctx['ref'],
            $ctx['ref_label'],
            $ctx['pulse'],
            $ctx['esocial_pending'],
        );
    }

    /** @return array<string, mixed> */
    private function collectTickerContext(Empresa $empresa): array
    {
        $this->feriasService->syncStatusByDate($empresa);

        $now = new \DateTimeImmutable();
        $ref = $now->format('Y-m');
        $monthStart = $now->modify('first day of this month')->setTime(0, 0);
        $folha = $this->folhaRepo->findOneByReferencia($empresa, $ref);
        $headcount = $this->funcionarioRepo->countByStatusGrouped($empresa);
        $ativos = $headcount['ATIVO'] ?? 0;
        $onboardingOpen = $this->onboarding->countOpen($empresa);
        $offboardingOpen = $this->offboarding->countOpen($empresa);
        $feriasPendentes = $this->feriasRepo->countByStatus($empresa, RhFerias::STATUS_SOLICITADA);
        $feriasEmGozo = $this->feriasRepo->countByStatus($empresa, RhFerias::STATUS_EM_GOZO);
        $openOnboarding = $this->onboardingRepo->findOpenRecent($empresa, 8);
        $semSalario = $this->funcionarioRepo->countActiveWithoutSalary($empresa);

        return [
            'ativos' => $ativos,
            'onboarding_open' => $onboardingOpen,
            'offboarding_open' => $offboardingOpen,
            'ferias_pendentes' => $feriasPendentes,
            'ferias_em_gozo' => $feriasEmGozo,
            'sem_salario' => $semSalario,
            'com_usuario' => $this->funcionarioRepo->countWithPlatformUser($empresa),
            'admitidos_mes' => $this->funcionarioRepo->countAdmittedSince($empresa, $monthStart),
            'concluidos_mes' => $this->onboardingRepo->countConcludedSince($empresa, $monthStart),
            'folha' => $folha,
            'ref' => $ref,
            'ref_label' => $this->formatCompetencia($ref),
            'pulse' => $this->computePulseScore(
                $feriasPendentes,
                $onboardingOpen,
                $offboardingOpen,
                $folha,
                $openOnboarding,
                $semSalario,
            ),
            'esocial_pending' => $this->esocialRepo->countPendingByEmpresa($empresa),
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

    /**
     * @param array{score: int, label: string, tone: string, hint: string} $pulse
     *
     * @return list<array{tag: string, title: string, text: string, icon: string, tone: string, route?: string, route_label?: string}>
     */
    private function buildTickerSlides(
        int $ativos,
        int $onboardingOpen,
        int $offboardingOpen,
        int $feriasPendentes,
        int $feriasEmGozo,
        int $semSalario,
        int $comUsuario,
        int $admitidosMes,
        int $concluidosMes,
        ?RhFolhaCompetencia $folha,
        string $ref,
        string $refLabel,
        array $pulse,
        int $esocialPending,
    ): array {
        $slides = [];

        if ($feriasPendentes > 0) {
            $slides[] = [
                'tag' => 'Operação',
                'title' => $feriasPendentes === 1
                    ? '1 férias aguardando aprovação'
                    : $feriasPendentes . ' férias aguardando aprovação',
                'text' => 'Responda solicitações antes do próximo fechamento para evitar atrasos na escala.',
                'icon' => 'fa-umbrella-beach',
                'tone' => 'amber',
                'route' => 'app_rh_ferias',
                'route_label' => 'Ver férias',
            ];
        }

        if ($onboardingOpen > 0) {
            $slides[] = [
                'tag' => 'Admissões',
                'title' => $onboardingOpen === 1
                    ? '1 admissão em andamento'
                    : $onboardingOpen . ' admissões em andamento',
                'text' => 'Complete checklists e documentos para liberar cadastro e folha do colaborador.',
                'icon' => 'fa-user-plus',
                'tone' => 'blue',
                'route' => 'app_rh_admissoes',
                'route_label' => 'Abrir admissões',
            ];
        }

        if ($offboardingOpen > 0) {
            $slides[] = [
                'tag' => 'Desligamentos',
                'title' => $offboardingOpen === 1
                    ? '1 desligamento ativo'
                    : $offboardingOpen . ' desligamentos ativos',
                'text' => 'Revise rescisão, entrega de equipamentos e eventos de saída no eSocial.',
                'icon' => 'fa-door-open',
                'tone' => 'rose',
                'route' => 'app_rh_demissoes',
                'route_label' => 'Ver desligamentos',
            ];
        }

        if ($esocialPending > 0) {
            $slides[] = [
                'tag' => 'Compliance',
                'title' => $esocialPending === 1
                    ? '1 lote eSocial na fila'
                    : $esocialPending . ' lotes eSocial na fila',
                'text' => 'Processe ou reenvie lotes pendentes para manter a folha em dia com o governo.',
                'icon' => 'fa-landmark',
                'tone' => 'amber',
                'route' => 'app_rh_esocial',
                'route_label' => 'Fila eSocial',
            ];
        }

        if (!$folha) {
            $slides[] = [
                'tag' => 'Folha',
                'title' => 'Folha de ' . $refLabel . ' ainda não gerada',
                'text' => 'Gere a competência atual para calcular proventos, descontos e encargos.',
                'icon' => 'fa-file-invoice-dollar',
                'tone' => 'warning',
                'route' => 'app_rh_folha',
                'route_label' => 'Ir para folha',
            ];
        } elseif (!$folha->isFechada()) {
            $slides[] = [
                'tag' => 'Folha',
                'title' => 'Competência ' . $refLabel . ' aberta',
                'text' => 'Revise lançamentos e feche a folha após conferir totais e holerites.',
                'icon' => 'fa-lock-open',
                'tone' => 'blue',
                'route' => 'app_rh_folha_show',
                'route_label' => 'Revisar folha',
                'route_params' => ['id' => $folha->getId()],
            ];
        }

        if ($semSalario > 0) {
            $slides[] = [
                'tag' => 'Cadastro',
                'title' => $semSalario === 1
                    ? '1 colaborador sem salário cadastrado'
                    : $semSalario . ' colaboradores sem salário cadastrado',
                'text' => 'Salário base é obrigatório para gerar folha e calcular encargos corretamente.',
                'icon' => 'fa-coins',
                'tone' => 'warning',
                'route' => 'app_rh_funcionarios',
                'route_label' => 'Ver cadastros',
            ];
        }

        if ($admitidosMes > 0) {
            $slides[] = [
                'tag' => 'Movimento',
                'title' => $admitidosMes === 1
                    ? '1 admissão concluída este mês'
                    : $admitidosMes . ' admissões concluídas este mês',
                'text' => $concluidosMes > 0
                    ? $concluidosMes . ' processo(s) finalizado(s) no fluxo de onboarding.'
                    : 'Time em expansão — acompanhe integração e documentação.',
                'icon' => 'fa-chart-line',
                'tone' => 'green',
                'route' => 'app_rh_funcionarios',
                'route_label' => 'Ver colaboradores',
            ];
        }

        if ($feriasEmGozo > 0) {
            $slides[] = [
                'tag' => 'Operação',
                'title' => $feriasEmGozo === 1
                    ? '1 colaborador em férias agora'
                    : $feriasEmGozo . ' colaboradores em férias agora',
                'text' => 'Planeje cobertura de turnos e retorno conforme calendário da equipe.',
                'icon' => 'fa-sun',
                'tone' => 'green',
                'route' => 'app_rh_ferias',
                'route_label' => 'Calendário',
            ];
        }

        $slides[] = [
            'tag' => 'Headcount',
            'title' => $ativos === 1 ? '1 colaborador ativo' : $ativos . ' colaboradores ativos',
            'text' => $comUsuario > 0
                ? $comUsuario . ' com acesso ao portal — holerite, férias e dados pessoais.'
                : 'Cadastre usuários no portal para self-service de holerite e férias.',
            'icon' => 'fa-users',
            'tone' => 'blue',
            'route' => 'app_rh_funcionarios',
            'route_label' => 'Ver equipe',
        ];

        if ($pulse['score'] >= 85) {
            $slides[] = [
                'tag' => 'RH Pulse',
                'title' => 'Saúde operacional: ' . $pulse['label'],
                'text' => $pulse['hint'],
                'icon' => 'fa-heart-pulse',
                'tone' => 'green',
            ];
        }

        $tips = $this->buildTickerTips($refLabel);
        $dayIndex = (int) (new \DateTimeImmutable())->format('j') % \count($tips);
        $rotatedTips = array_merge(
            \array_slice($tips, $dayIndex),
            \array_slice($tips, 0, $dayIndex),
        );

        foreach ($rotatedTips as $tip) {
            if (\count($slides) >= 8) {
                break;
            }
            $slides[] = $tip;
        }

        return \array_slice($slides, 0, 8);
    }

    /** @return list<array{tag: string, title: string, text: string, icon: string, tone: string, route?: string, route_label?: string}> */
    private function buildTickerTips(string $refLabel): array
    {
        return [
            [
                'tag' => 'Dica RH',
                'title' => 'Portal do colaborador',
                'text' => 'Holerites, solicitação de férias e atualização de dados em um só lugar.',
                'icon' => 'fa-id-badge',
                'tone' => 'violet',
                'route' => 'app_rh_portal',
                'route_label' => 'Abrir portal',
            ],
            [
                'tag' => 'CLT',
                'title' => 'Prazo de férias',
                'text' => 'Boas práticas: analise pedidos em até 5 dias úteis e registre gozo com antecedência.',
                'icon' => 'fa-scale-balanced',
                'tone' => 'amber',
                'route' => 'app_rh_ferias',
                'route_label' => 'Fluxo de férias',
            ],
            [
                'tag' => 'Compliance',
                'title' => 'eSocial em dia',
                'text' => 'Enfileire eventos de admissão, alteração e desligamento antes de fechar ' . $refLabel . '.',
                'icon' => 'fa-shield-halved',
                'tone' => 'amber',
                'route' => 'app_rh_esocial',
                'route_label' => 'Enviar lotes',
            ],
            [
                'tag' => 'Onboarding',
                'title' => 'Checklist de admissão',
                'text' => 'Documentos, contrato e dados bancários completos evitam retrabalho na folha.',
                'icon' => 'fa-clipboard-check',
                'tone' => 'blue',
                'route' => 'app_rh_admissoes',
                'route_label' => 'Ver processos',
            ],
            [
                'tag' => 'Folha',
                'title' => 'Fechamento mensal',
                'text' => 'Confira totais, INSS e FGTS antes de liberar holerites para a equipe.',
                'icon' => 'fa-calculator',
                'tone' => 'blue',
                'route' => 'app_rh_folha',
                'route_label' => 'Competências',
            ],
        ];
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
