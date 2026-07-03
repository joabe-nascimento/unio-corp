<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;

/**
 * Notícias, atualizações e conteúdo profissional da tela de boas-vindas.
 */
final class WelcomeContentService
{
    /** @var list<array{id: string, title: string, text: string, icon: string, layouts: list<string>}> */
    private const INSIGHTS = [
        [
            'id' => 'tenant-scale',
            'title' => 'Escale com governança',
            'text' => 'Revise empresas ativas e perfis de acesso antes de liberar novos núcleos para equipes operacionais.',
            'icon' => 'fa-building-shield',
            'layouts' => ['tenant'],
        ],
        [
            'id' => 'gestor-rhythm',
            'title' => 'Ritmo de gestão semanal',
            'text' => 'Combine dashboard, Núcleo de Operações e indicadores analíticos em uma rotina fixa de segunda-feira.',
            'icon' => 'fa-calendar-check',
            'layouts' => ['gestor'],
        ],
        [
            'id' => 'supervisor-team',
            'title' => 'Foco na equipe',
            'text' => 'Priorize ponto, férias e comunicação interna antes de expandir para módulos estratégicos.',
            'icon' => 'fa-people-group',
            'layouts' => ['supervisor'],
        ],
        [
            'id' => 'member-profile',
            'title' => 'Mantenha seu perfil atualizado',
            'text' => 'Dados corretos garantem holerites, comunicados e trilhas de desenvolvimento no lugar certo.',
            'icon' => 'fa-user-pen',
            'layouts' => ['membro'],
        ],
        [
            'id' => 'helix-copilot',
            'title' => 'Use a Vitória como copiloto',
            'text' => 'Pergunte sobre navegação, próximos passos e atalhos — a assistente conhece os núcleos do seu perfil.',
            'icon' => 'fa-wand-magic-sparkles',
            'layouts' => ['tenant', 'gestor', 'supervisor', 'membro'],
        ],
    ];

    public function __construct(
        private NavigationService $navigation,
        private WelcomeService $welcome,
        private WelcomeNewsFeedService $newsFeed,
        private WelcomeUpdatesIntelligenceService $updatesIntel,
    ) {}

    /**
     * @return array{
     *     news: list<array<string, mixed>>,
     *     news_meta: array{
     *         unread_count: int,
     *         read_count: int,
     *         read_recent_count: int,
     *         total: int,
     *         filter: string
     *     },
     *     updates: list<array<string, mixed>>,
     *     updates_meta: array{dynamic_count: int},
     *     insights: list<array<string, mixed>>,
     *     summary_cards: list<array<string, mixed>>
     * }
     */
    public function build(User $user, ?Empresa $empresa, string $layout, int $empresasCount = 1): array
    {
        $snapshot = $this->welcome->getWelcomeSnapshot($user);
        $newsPayload = $this->newsFeed->getFeedPayloadForUser($user, $empresa, $layout);
        $updatesPayload = $this->updatesIntel->buildPayload($user, $empresa, $layout, $empresasCount);

        return [
            'news' => $newsPayload['items'],
            'news_meta' => [
                'unread_count' => $newsPayload['unread_count'],
                'read_count' => $newsPayload['read_count'],
                'read_recent_count' => $newsPayload['read_recent_count'],
                'total' => $newsPayload['total'],
                'filter' => $newsPayload['filter'],
            ],
            'updates' => $updatesPayload['items'],
            'updates_meta' => [
                'dynamic_count' => $updatesPayload['dynamic_count'],
            ],
            'insights' => $this->getInsights($layout),
            'summary_cards' => $this->getSummaryCards($user, $empresa, $layout, $snapshot),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function getInsights(string $layout): array
    {
        $items = [];
        foreach (self::INSIGHTS as $insight) {
            if (!\in_array($layout, $insight['layouts'], true)
                && !($layout === 'platform_owner' && \in_array('tenant', $insight['layouts'], true))) {
                continue;
            }
            $items[] = $insight;
        }

        return \array_slice($items, 0, 3);
    }

    /**
     * @param array{hub_count: int, novidade_count: int} $snapshot
     *
     * @return list<array<string, mixed>>
     */
    private function getSummaryCards(User $user, ?Empresa $empresa, string $layout, array $snapshot): array
    {
        $cards = [];

        if ($empresa !== null) {
            $cards[] = [
                'id' => 'empresa',
                'icon' => 'fa-building',
                'label' => 'Área de trabalho',
                'value' => $empresa->getNome() ?? '—',
                'hint' => $empresa->getSetor() ? 'Setor: ' . $empresa->getSetor() : 'Empresa ativa na sessão',
            ];
        }

        $cards[] = [
            'id' => 'hubs',
            'icon' => 'fa-layer-group',
            'label' => 'Hubs liberados',
            'value' => (string) $snapshot['hub_count'],
            'hint' => 'Pontos de entrada disponíveis no seu perfil',
        ];

        if ($snapshot['novidade_count'] > 0) {
            $cards[] = [
                'id' => 'novidades',
                'icon' => 'fa-star',
                'label' => 'Novidades',
                'value' => (string) $snapshot['novidade_count'],
                'hint' => 'Recursos novos nesta área de trabalho',
            ];
        }

        $cards[] = [
            'id' => 'perfil',
            'icon' => 'fa-id-badge',
            'label' => 'Seu perfil',
            'value' => $user->getPerfilLabel(),
            'hint' => match ($layout) {
                'tenant' => 'Visão completa da plataforma',
                'gestor' => 'Gestão de núcleos e equipes',
                'supervisor' => 'Supervisão operacional',
                default => 'Acesso colaborador',
            },
        ];

        return $cards;
    }
}
