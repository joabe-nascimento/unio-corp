<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use App\Security\ProductGrantAccess;

/**
 * Tour contextual do shell — fluxos sob demanda via hub de Ajuda.
 */
class OnboardingTourService
{
    private const STEP_ICONS = [
        'identity' => 'fa-user-circle',
        'search' => 'fa-magnifying-glass',
        'cortex' => 'fa-brain',
        'hubs' => 'fa-layer-group',
        'helix' => 'fa-sparkles',
        'profile_tenant' => 'fa-shield-halved',
        'profile_gestor' => 'fa-briefcase',
        'profile_supervisor' => 'fa-users-gear',
        'profile_membro' => 'fa-id-badge',
        'checklist' => 'fa-list-check',
    ];

    public function __construct(
        private NavigationService $navigation,
        private ProductGrantAccess $grants,
        private OnboardingProgressService $onboardingProgress,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     profile: string,
     *     layout: string,
     *     checklist: array{visible: bool, percent: int, shell_tour_done: bool, completed: int, total: int},
     *     steps: list<array<string, mixed>>,
     *     flows: list<array<string, mixed>>
     * }
     */
    public function build(User $user, ?Empresa $empresa = null, int $empresasCount = 0, ?string $route = null): array
    {
        $layout = $this->navigation->getLayout($user);
        $checklist = $this->onboardingProgress->build($user, $empresa, $empresasCount);
        $shellTourDone = $this->onboardingProgress->isStepComplete('shell_tour', $user);
        $steps = $this->resolveSteps($user, $layout, $checklist);

        return [
            'enabled' => $steps !== [],
            'profile' => $user->getPerfil(),
            'layout' => $layout,
            'checklist' => [
                'visible' => $checklist['visible'],
                'percent' => $checklist['percent'],
                'shell_tour_done' => $shellTourDone,
                'completed' => $checklist['completed'],
                'total' => $checklist['total'],
            ],
            'steps' => $steps,
            'flows' => $this->resolveFlows($user, $steps, $checklist, $shellTourDone),
        ];
    }

    /**
     * @param array{visible: bool, percent: int, completed: int, total: int} $checklist
     * @param list<array<string, mixed>> $steps
     *
     * @return list<array<string, mixed>>
     */
    private function resolveFlows(User $user, array $steps, array $checklist, bool $shellTourDone): array
    {
        if ($steps === []) {
            return [];
        }

        $stepIds = array_column($steps, 'id');
        $flows = [];

        $flows[] = [
            'id' => 'full',
            'label' => 'Tour completo da interface',
            'description' => sprintf(
                'Percorra %d passos: conta, busca, núcleos, assistente e mais.',
                \count($steps),
            ),
            'icon' => 'fa-route',
            'step_ids' => $stepIds,
            'marks_complete' => true,
            'featured' => true,
            'done' => $shellTourDone,
        ];

        foreach ($steps as $step) {
            if ($step['id'] === 'checklist') {
                if ($checklist['visible']) {
                    $flows[] = [
                        'id' => 'checklist-scroll',
                        'label' => 'Checklist de primeiros passos',
                        'description' => sprintf(
                            '%d de %d concluídos — veja as tarefas pendentes.',
                            $checklist['completed'],
                            $checklist['total'],
                        ),
                        'icon' => 'fa-list-check',
                        'action' => 'scroll-checklist',
                        'marks_complete' => false,
                    ];
                }
                continue;
            }

            $flows[] = [
                'id' => 'step-' . $step['id'],
                'label' => $step['title'],
                'description' => $step['body'],
                'icon' => self::STEP_ICONS[$step['id']] ?? 'fa-circle-info',
                'step_ids' => [$step['id']],
                'marks_complete' => false,
            ];
        }

        $flows[] = [
            'id' => 'shortcuts',
            'label' => 'Atalhos de teclado',
            'description' => 'Ctrl+K ou ⌘K abre a busca global em qualquer tela.',
            'icon' => 'fa-keyboard',
            'action' => 'focus-search',
            'marks_complete' => false,
        ];

        return $this->appendModuleGuideFlows($user, $flows);
    }

    /**
     * @param list<array<string, mixed>> $flows
     *
     * @return list<array<string, mixed>>
     */
    private function appendModuleGuideFlows(User $user, array $flows): array
    {
        if ($this->grants->isRouteAllowed($user, 'app_rh')) {
            $flows[] = [
                'id' => 'guide-rh',
                'label' => 'RH — visão geral',
                'description' => 'Funcionários, admissões, férias e folha no Núcleo de Operações.',
                'icon' => 'fa-id-card',
                'action' => 'navigate',
                'navigate' => 'app_rh',
                'marks_complete' => false,
            ];
        }

        if ($this->grants->isRouteAllowed($user, 'app_recrutamento')) {
            $flows[] = [
                'id' => 'guide-recrutamento',
                'label' => 'Recrutamento — vagas e pipeline',
                'description' => 'Publique vagas, acompanhe candidatos e mova etapas no pipeline.',
                'icon' => 'fa-user-tie',
                'action' => 'navigate',
                'navigate' => 'app_recrutamento',
                'marks_complete' => false,
            ];
        }

        return $flows;
    }

    /**
     * @param array{visible: bool, percent: int, completed: int, total: int, steps: list<array<string, mixed>>} $checklist
     *
     * @return list<array<string, mixed>>
     */
    private function resolveSteps(User $user, string $layout, array $checklist): array
    {
        $hasHubs = $this->navigation->showHubOperacoes($user)
            || $this->navigation->showHubTalentos($user)
            || $this->navigation->showHubMaturidade($user)
            || $this->navigation->showHubRecrutamento($user)
            || $this->navigation->showPlataforma($user)
            || $this->navigation->getVisiblePlannedHubs($user) !== [];

        $steps = [
            [
                'id' => 'identity',
                'target' => '[data-tour="identity"]',
                'title' => 'Sua conta e workspace',
                'body' => 'Troque de empresa, ajuste preferências e acesse seu perfil por aqui.',
                'placement' => 'right',
                'radius' => 10,
                'requires_sidebar' => true,
                'mobile_targets' => ['[data-tour="identity"]', '[data-tour="mobile-menu"]'],
                'mobile_placement' => 'bottom',
                'mobile_body' => 'Abra o Menu (barra inferior) e use o topo da sidebar para trocar de empresa e acessar seu perfil.',
            ],
            [
                'id' => 'search',
                'target' => '[data-tour="search"]',
                'title' => 'Busca global',
                'body' => 'Encontre núcleos, apps e membros. Use Ctrl+K (ou ⌘K no Mac) em qualquer tela.',
                'placement' => 'bottom',
                'radius' => 14,
                'mobile_targets' => ['[data-tour="help"]'],
                'mobile_placement' => 'bottom',
                'mobile_body' => 'Toque em Ajuda no topo para guias e atalhos. No desktop, a busca fica aqui no header (Ctrl+K ou ⌘K).',
            ],
        ];

        if ($this->navigation->showCortex($user)) {
            $steps[] = [
                'id' => 'cortex',
                'target' => '[data-tour="cortex"]',
                'title' => 'Unio Cortex',
                'body' => 'Inteligência e visão consolidada dos dados da sua operação.',
                'placement' => 'right',
                'requires_sidebar' => true,
                'mobile_targets' => ['[data-tour="cortex-mobile"]', '[data-tour="cortex"]'],
                'mobile_placement' => 'top',
            ];
        }

        if ($hasHubs) {
            $steps[] = [
                'id' => 'hubs',
                'target' => '[data-tour="hub-picker-core"]',
                'targets' => [
                    '[data-tour="hub-picker-core"]',
                    '[data-hub-pick="operacoes"]',
                ],
                'zone' => 'hub-picker',
                'prepare' => 'show-hub-picker',
                'requires_sidebar' => true,
                'title' => 'Núcleos da plataforma',
                'body' => 'Escolha um núcleo na lista — cada um agrupa módulos e apps por domínio.',
                'placement' => 'right',
                'mobile_targets' => [
                    '[data-tour="hub-picker-core"]',
                    '[data-hub-pick="operacoes"]',
                    '[data-tour="hub-operacoes-mobile"]',
                ],
                'mobile_placement' => 'bottom',
                'mobile_body' => 'Abra o Menu ou use o atalho Operações na barra inferior para entrar em um núcleo.',
            ];
        }

        $steps[] = [
            'id' => 'helix',
            'target' => '[data-tour="helix"]',
            'title' => 'Vitória — assistente',
            'body' => 'Tire dúvidas e acelere tarefas com a assistente virtual da Unio.',
            'placement' => 'bottom',
            'highlight' => 'circle',
            'mobile_placement' => 'bottom',
        ];

        $profileStep = match ($layout) {
            'tenant' => $this->navigation->showPlataforma($user)
                ? [
                    'id' => 'profile_tenant',
                    'target' => '[data-tour="hub-admin"]',
                    'targets' => [
                        '[data-tour="hub-admin"]',
                        '[data-hub-pick="admin"]',
                        '[data-tour="hub-platform"]',
                    ],
                    'prepare' => 'show-hub-picker',
                    'requires_sidebar' => true,
                    'title' => 'Administração da plataforma',
                    'body' => 'No grupo Plataforma, abra o núcleo para gerenciar usuários, empresas e configurações.',
                    'placement' => 'right',
                    'mobile_targets' => [
                        '[data-tour="hub-admin"]',
                        '[data-hub-pick="admin"]',
                        '[data-tour="mobile-menu"]',
                    ],
                    'mobile_placement' => 'bottom',
                    'mobile_body' => 'Abra o Menu e, em Plataforma, entre no núcleo Administração.',
                ]
                : null,
            'gestor' => [
                'id' => 'profile_gestor',
                'target' => '[data-tour="notifications"]',
                'title' => 'Gestão do dia a dia',
                'body' => 'Acompanhe notificações, hubs de RH e talentos para liderar equipes e processos.',
                'placement' => 'right',
                'requires_sidebar' => true,
                'mobile_targets' => ['[data-tour="notifications-mobile"]', '[data-tour="notifications"]'],
                'mobile_placement' => 'top',
            ],
            'supervisor' => $hasHubs
                ? [
                    'id' => 'profile_supervisor',
                    'target' => '[data-tour="hub-operacoes"]',
                    'targets' => [
                        '[data-tour="hub-operacoes"]',
                        '[data-hub-pick="operacoes"]',
                        '[data-hub-pick="recrutamento"]',
                    ],
                    'prepare' => 'show-hub-picker',
                    'requires_sidebar' => true,
                    'title' => 'Supervisão operacional',
                    'body' => 'Entre no Núcleo de Operações ou Recrutamento para acompanhar equipes e fluxos.',
                    'placement' => 'right',
                    'mobile_targets' => [
                        '[data-tour="hub-operacoes-mobile"]',
                        '[data-tour="hub-operacoes"]',
                        '[data-hub-pick="operacoes"]',
                    ],
                    'mobile_placement' => 'top',
                ]
                : [
                    'id' => 'profile_supervisor',
                    'target' => '[data-tour="notifications"]',
                    'title' => 'Supervisão operacional',
                    'body' => 'Use notificações e chat para acompanhar a operação do dia a dia.',
                    'placement' => 'right',
                    'mobile_targets' => ['[data-tour="notifications-mobile"]', '[data-tour="notifications"]'],
                    'mobile_placement' => 'top',
                ],
            default => $this->grants->grantAtLeast($user, 'product_rh', 'portal', 'MEMBRO')
                ? [
                    'id' => 'profile_membro',
                    'target' => '[data-tour="cortex"]',
                    'title' => 'Portal do colaborador',
                    'body' => 'Acesse holerites, férias e comunicados pelo módulo RH quando disponível no seu núcleo.',
                    'placement' => 'right',
                ]
                : [
                    'id' => 'profile_membro',
                    'target' => '[data-tour="notifications"]',
                    'title' => 'Fique por dentro',
                    'body' => 'Notificações e chat mantêm você alinhado com a equipe e a empresa.',
                    'placement' => 'right',
                ],
        };

        if (\is_array($profileStep)) {
            $steps[] = $profileStep;
        }

        if ($checklist['visible']) {
            $remaining = max(0, $checklist['total'] - $checklist['completed']);
            $steps[] = [
                'id' => 'checklist',
                'target' => '[data-onboarding-checklist]',
                'title' => 'Checklist de primeiros passos',
                'body' => $remaining > 0
                    ? sprintf(
                        'Conclua as %d tarefa(s) restante(s) do checklist para deixar sua área pronta.',
                        $remaining,
                    )
                    : 'Ótimo! Você concluiu todos os primeiros passos da plataforma.',
                'placement' => 'top',
                'mobile_placement' => 'bottom',
            ];
        }

        return array_values(array_filter($steps, static function (array $step): bool {
            return ($step['target'] ?? '') !== '';
        }));
    }
}
