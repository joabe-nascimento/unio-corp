<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;

/**
 * Conteúdo de apresentação da tela /bem-vindo (pós seleção de workspace).
 */
final class WelcomePresentationService
{
    /** @var list<array{icon: string, title: string, text: string}> */
    private const HIGHLIGHTS = [
        [
            'icon' => 'fa-people-group',
            'title' => 'Gestão de pessoas unificada',
            'text' => 'RH, equipes e colaboradores em um só ecossistema, com dados sempre atualizados.',
        ],
        [
            'icon' => 'fa-layer-group',
            'title' => 'Hubs especializados',
            'text' => 'Operações, Talentos e Maturidade organizados para cada necessidade do negócio.',
        ],
        [
            'icon' => 'fa-chart-line',
            'title' => 'Indicadores ao vivo',
            'text' => 'Gráficos e métricas construídos sobre o que está registrado na sua área de trabalho.',
        ],
        [
            'icon' => 'fa-shield-halved',
            'title' => 'Acesso por perfil',
            'text' => 'Cada pessoa vê apenas o que importa, com segurança e governança da plataforma.',
        ],
    ];

    public function __construct(
        private NavigationService $navigation,
        private DashboardStatsService $dashboardStats,
        private WelcomeService $welcome,
        private PlatformConfigService $platformConfig,
    ) {}

    /**
     * @return array{
     *     pitch: string,
     *     intro: string,
     *     eyebrow: string,
     *     highlights: list<array{icon: string, title: string, text: string}>,
     *     metrics: list<array{value: int, label: string, icon: string, route?: string}>,
     *     journey: list<array{step: int, title: string, text: string, icon: string, route: string, cta: string}>,
     *     empresa_brief: ?array{nome: string, setor: ?string, ativa: bool, member_since: ?string},
     *     first_hub: ?array{title: string, route: string}
     * }
     */
    public function build(User $user, ?Empresa $empresa, int $empresasCount): array
    {
        $layout = $this->navigation->getLayout($user);
        $hubs = $this->welcome->getHubsForUser($user);
        $config = $this->platformConfig->all();
        $platformName = (string) ($config['plataforma_nome'] ?? 'Unio');
        $tagline = (string) ($config['plataforma_tagline'] ?? 'Plataforma de Gestão de Pessoas');

        return [
            'pitch' => $tagline,
            'intro' => $this->buildIntro($user, $empresa, $layout, $platformName),
            'eyebrow' => 'Você está na ' . $platformName,
            'highlights' => self::HIGHLIGHTS,
            'metrics' => \array_slice(
                $this->dashboardStats->getKpis($user, $empresa, $layout, $empresasCount),
                0,
                6,
            ),
            'journey' => $this->buildJourney($user, $hubs, $empresa),
            'empresa_brief' => $this->buildEmpresaBrief($empresa),
            'first_hub' => $this->pickPrimaryHubForCta($hubs),
        ];
    }

    /** @param list<array<string, mixed>> $hubs */
    /** @return ?array{title: string, route: string, icon?: string} */
    private function pickPrimaryHubForCta(array $hubs): ?array
    {
        foreach ($hubs as $hub) {
            if (($hub['id'] ?? '') === 'cortex') {
                continue;
            }
            if (!empty($hub['route']) && !empty($hub['title'])) {
                return $hub;
            }
        }

        return null;
    }

    private function buildIntro(User $user, ?Empresa $empresa, string $layout, string $platformName): string
    {
        $firstName = explode(' ', $user->getNome() ?? '')[0] ?: 'você';
        $empresaNome = $empresa?->getNome();

        return match ($layout) {
            'tenant' => sprintf(
                '%s, você tem visão completa da %s. Gerencie empresas, usuários e hubs em um ambiente preparado para escalar com segurança.',
                $firstName,
                $platformName,
            ),
            'gestor' => $empresaNome
                ? sprintf(
                    '%s, esta é a central de gestão de %s. Acompanhe equipes, processos e entregas com clareza desde o primeiro acesso.',
                    $firstName,
                    $empresaNome,
                )
                : sprintf('%s, bem-vindo à sua área de gestão na %s.', $firstName, $platformName),
            'supervisor' => $empresaNome
                ? sprintf(
                    '%s, tudo pronto para você liderar em %s. Visualize indicadores da equipe e acesse os hubs liberados para o seu perfil.',
                    $firstName,
                    $empresaNome,
                )
                : sprintf('%s, sua área de supervisão na %s está ativa.', $firstName, $platformName),
            default => $empresaNome
                ? sprintf(
                    '%s, você entrou em %s. Explore os recursos disponíveis e comece pelo hub indicado para o seu perfil.',
                    $firstName,
                    $empresaNome,
                )
                : sprintf('%s, seja bem-vindo à %s.', $firstName, $platformName),
        };
    }

    /** @param list<array<string, mixed>> $hubs */
    /** @return list<array{step: int, title: string, text: string, icon: string, route: string, cta: string}> */
    private function buildJourney(User $user, array $hubs, ?Empresa $empresa): array
    {
        $steps = [];
        $step = 1;

        $primaryHub = null;
        foreach ($hubs as $hub) {
            if (($hub['id'] ?? '') !== 'cortex' && !empty($hub['route']) && !empty($hub['title'])) {
                $primaryHub = $hub;
                break;
            }
        }
        if ($primaryHub !== null) {
            $steps[] = [
                'step' => $step++,
                'title' => 'Conheça seu hub principal',
                'text' => 'Comece por ' . $primaryHub['title'] . ' — o ponto de partida recomendado para o seu perfil.',
                'icon' => $primaryHub['icon'] ?? 'fa-briefcase',
                'route' => $primaryHub['route'],
                'cta' => 'Abrir hub',
            ];
        }

        $steps[] = [
            'step' => $step++,
            'title' => 'Visão geral no dashboard',
            'text' => 'Acesse KPIs, módulos e atalhos operacionais na central de comando.',
            'icon' => 'fa-gauge-high',
            'route' => 'app_dashboard',
            'cta' => 'Ir ao dashboard',
        ];

        if ($empresa !== null) {
            $steps[] = [
                'step' => $step++,
                'title' => 'Explore os indicadores',
                'text' => 'Veja gráficos com dados reais registrados nesta área de trabalho.',
                'icon' => 'fa-chart-pie',
                'route' => '#welcome-analytics',
                'cta' => 'Ver indicadores',
            ];
        }

        $steps[] = [
            'step' => $step,
            'title' => 'Ajuste sua experiência',
            'text' => 'Personalize seções e layout desta tela conforme sua preferência.',
            'icon' => 'fa-sliders',
            'route' => '#welcome-personalize',
            'cta' => 'Personalizar',
        ];

        return $steps;
    }

    /** @return ?array{nome: string, setor: ?string, ativa: bool, member_since: ?string} */
    private function buildEmpresaBrief(?Empresa $empresa): ?array
    {
        if ($empresa === null) {
            return null;
        }

        return [
            'nome' => $empresa->getNome() ?? '',
            'setor' => $empresa->getSetor(),
            'ativa' => $empresa->isAtivo(),
            'member_since' => $empresa->getCriadoEm()->format('m/Y'),
        ];
    }
}
