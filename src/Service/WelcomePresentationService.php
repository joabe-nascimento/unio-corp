<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;

/**
 * Conteúdo de apresentação da tela /bem-vindo (pós seleção de workspace).
 */
final class WelcomePresentationService
{
    public function __construct(
        private NavigationService $navigation,
        private DashboardStatsService $dashboardStats,
        private WelcomeService $welcome,
    ) {}

    /**
     * @return array{
     *     hero_subtitle: string,
     *     metrics: list<array{value: int, label: string, icon: string, route?: string}>,
     *     empresa_brief: ?array{nome: string, setor: ?string, ativa: bool, member_since: ?string},
     *     first_hub: ?array{title: string, route: string, icon?: string}
     * }
     */
    public function build(User $user, ?Empresa $empresa, int $empresasCount): array
    {
        $layout = $this->navigation->getLayout($user);
        $hubs = $this->welcome->getHubsForUser($user);

        return [
            'hero_subtitle' => $this->buildHeroSubtitle($user, $empresa, $layout),
            'metrics' => \array_slice(
                $this->dashboardStats->getKpis($user, $empresa, $layout, $empresasCount),
                0,
                6,
            ),
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

    private function buildHeroSubtitle(User $user, ?Empresa $empresa, string $layout): string
    {
        $empresaNome = $empresa?->getNome();

        return match ($layout) {
            'tenant' => 'Seu workspace está pronto. Escolha por onde começar.',
            'gestor' => $empresaNome
                ? sprintf('Central de gestão de %s — escolha o próximo passo.', $empresaNome)
                : 'Sua área de gestão está ativa. Escolha por onde começar.',
            'supervisor' => $empresaNome
                ? sprintf('Área de supervisão em %s — acesse os núcleos do seu perfil.', $empresaNome)
                : 'Sua área de supervisão está ativa.',
            default => $empresaNome
                ? sprintf('Você entrou em %s. Explore os recursos disponíveis.', $empresaNome)
                : 'Explore os recursos disponíveis e comece pelo hub indicado.',
        };
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
