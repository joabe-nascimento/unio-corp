<?php

namespace App\Service\Organismo;

use App\Entity\User;
use App\Service\NavigationService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Práticas acessíveis — substituem o picker de núcleos na navegação Organismo.
 *
 * @return list<array{id: string, label: string, icon: string, route: string, group: string, route_prefixes: list<string>}>
 */
final class OrganismoPraticaRegistry
{
    public function __construct(
        private NavigationService $navigation,
        private UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @return list<array{id: string, label: string, icon: string, route: string, url: string, group: string, route_prefixes: list<string>}>
     */
    public function getForUser(User $user): array
    {
        $items = [];

        if ($this->navigation->showHubOperacoes($user)) {
            if ($this->navigation->showModuloRh($user)) {
                $items[] = $this->item('vida_membro', 'Vida do membro', 'fa-id-badge', 'app_rh_funcionarios', 'Operação', ['app_rh', 'app_hub_operacoes']);
            }
            if ($this->navigation->showModuloRh($user)) {
                $items[] = $this->item('admissao', 'Admissões', 'fa-user-plus', 'app_rh_admissoes', 'Operação', ['app_rh_admissoes']);
            }
            if ($this->navigation->showModuloPessoas($user)) {
                $items[] = $this->item('pessoas', 'Pessoas e círculos', 'fa-people-group', 'app_pessoas', 'Operação', ['app_pessoas']);
            }
        }

        if ($this->navigation->showHubRecrutamento($user)) {
            $items[] = $this->item('crescimento', 'Crescimento', 'fa-seedling', 'app_recrutamento', 'Crescimento', ['app_recrutamento', 'app_talentos']);
        } elseif ($this->navigation->showHubTalentos($user)) {
            $items[] = $this->item('crescimento', 'Crescimento', 'fa-seedling', 'app_talentos', 'Crescimento', ['app_talentos']);
        }

        if ($this->navigation->showHubMaturidade($user)) {
            $items[] = $this->item('maturidade', 'Maturidade', 'fa-gauge-high', 'app_maturidade', 'Crescimento', ['app_maturidade']);
        }

        foreach ($this->navigation->getVisiblePlannedHubs($user) as $hub) {
            $id = (string) ($hub['id'] ?? '');
            if ($id === 'ti') {
                $items[] = $this->item('sustentacao', 'Sustentação TI', 'fa-server', 'app_ti', 'Sustentação', ['app_ti']);
            } elseif ($id === 'pos_operatorio') {
                $items[] = $this->item(
                    'cuidado',
                    'Cuidado contínuo',
                    'fa-heart-pulse',
                    'app_maturidade',
                    'Operação',
                    ['app_maturidade', 'app_pos_operatorio'],
                );
            } elseif ($id === 'integracoes') {
                $items[] = $this->item('sincronia', 'Sincronia', 'fa-plug', (string) ($hub['route'] ?? 'app_integracoes'), 'Sustentação', ['app_integracoes']);
            } elseif ($id === 'inovacao') {
                $items[] = $this->item('inovacao', 'Inovação', 'fa-lightbulb', (string) ($hub['route'] ?? 'app_inovacao'), 'Crescimento', ['app_inovacao']);
            }
        }

        if ($this->navigation->showPlataforma($user)) {
            $items[] = $this->item('observatorio', 'Observatório', 'fa-telescope', 'app_admin', 'Observatório', ['app_admin']);
        }

        return $items;
    }

    /**
     * @return array<string, list<array{id: string, label: string, icon: string, route: string, url: string, group: string, route_prefixes: list<string>}>>
     */
    public function getGroupedForUser(User $user): array
    {
        $grouped = [];
        foreach ($this->getForUser($user) as $item) {
            $grouped[$item['group']][] = $item;
        }

        return $grouped;
    }

    /**
     * Prática ativa para a rota corrente (sub-nav contextual).
     *
     * @return array{id: string, label: string, icon: string, route: string, url: string, group: string, route_prefixes: list<string>}|null
     */
    public function resolveActiveForRoute(User $user, ?string $route): ?array
    {
        if ($route === null || $route === '') {
            return null;
        }

        $best = null;
        $bestLen = 0;

        foreach ($this->getForUser($user) as $item) {
            foreach ($item['route_prefixes'] as $prefix) {
                if ($route === $prefix || str_starts_with($route, $prefix . '_')) {
                    $len = \strlen($prefix);
                    if ($len > $bestLen) {
                        $bestLen = $len;
                        $best = $item;
                    }
                }
            }
        }

        return $best;
    }

    public function isRouteInPractice(?string $route, array $practice): bool
    {
        if ($route === null || $route === '') {
            return false;
        }

        foreach ($practice['route_prefixes'] as $prefix) {
            if ($route === $prefix || str_starts_with($route, $prefix . '_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $routePrefixes
     *
     * @return array{id: string, label: string, icon: string, route: string, url: string, group: string, route_prefixes: list<string>}
     */
    private function item(string $id, string $label, string $icon, string $route, string $group, array $routePrefixes): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'icon' => $icon,
            'route' => $route,
            'url' => $this->router->generate($route),
            'group' => $group,
            'route_prefixes' => $routePrefixes,
        ];
    }
}
