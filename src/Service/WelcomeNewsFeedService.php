<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Feed de notícias da boas-vindas — catálogo editorial + pulso ao vivo + insights operacionais + leituras.
 */
final class WelcomeNewsFeedService
{
    private const TZ = 'America/Sao_Paulo';
    private const NEW_DAYS = 7;
    public const FEED_PAGE_SIZE = 4;
    public const FILTER_UNREAD = 'unread';
    public const FILTER_READ = 'read';

    /** @var list<array<string, mixed>>|null */
    private ?array $catalog = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private NavigationService $navigation,
        private DashboardStatsService $dashboardStats,
        private WelcomeNewsIntelligenceService $intelligence,
        private WelcomeNewsReadService $readService,
    ) {}

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     unread_count: int,
     *     read_count: int,
     *     read_recent_count: int,
     *     total: int,
     *     filter: string,
     *     refreshed: bool
     * }
     */
    public function getFeedPayloadForUser(
        User $user,
        ?Empresa $empresa,
        string $layout,
        int $limit = self::FEED_PAGE_SIZE,
        string $filter = self::FILTER_UNREAD,
        bool $discover = true,
    ): array {
        $readMap = $this->readService->getReadKeyMap($user);
        $allItems = $this->buildFeedItems($user, $empresa, $layout, $readMap);
        $refreshed = false;

        $unreadCount = 0;
        $readCount = 0;
        foreach ($allItems as $item) {
            if (!empty($item['is_read'])) {
                ++$readCount;
            } else {
                ++$unreadCount;
            }
        }

        $filtered = $this->filterItemsByReadState($allItems, $filter);

        if ($filter === self::FILTER_UNREAD && $filtered === [] && $discover) {
            $discovery = $this->intelligence->buildDiscoveryScan($user, $empresa, $layout);
            if ($discovery !== []) {
                $refreshed = true;
                $discoveryFormatted = [];
                foreach ($discovery as $article) {
                    $discoveryFormatted[] = $this->applyReadState(
                        $this->formatListItem($article),
                        $readMap,
                    );
                }
                $allItems = $this->sortFeed(array_merge($allItems, $discoveryFormatted));
                $filtered = $this->filterItemsByReadState($allItems, $filter);

                $unreadCount = 0;
                $readCount = 0;
                foreach ($allItems as $item) {
                    if (!empty($item['is_read'])) {
                        ++$readCount;
                    } else {
                        ++$unreadCount;
                    }
                }
            }
        }

        $limit = max(1, min(12, $limit));

        return [
            'items' => \array_slice($filtered, 0, $limit),
            'unread_count' => $unreadCount,
            'read_count' => $readCount,
            'read_recent_count' => $this->readService->countRecentReads($user),
            'total' => \count($allItems),
            'filter' => $filter,
            'refreshed' => $refreshed,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFeedForUser(
        User $user,
        ?Empresa $empresa,
        string $layout,
        int $limit = self::FEED_PAGE_SIZE,
        string $filter = self::FILTER_UNREAD,
    ): array {
        return $this->getFeedPayloadForUser($user, $empresa, $layout, $limit, $filter)['items'];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function findArticleForUser(User $user, string $slug, string $layout, ?Empresa $empresa): ?array
    {
        foreach ($this->buildAllItems($user, $empresa, $layout) as $article) {
            if (($article['slug'] ?? '') !== $slug) {
                continue;
            }

            $readMap = $this->readService->getReadKeyMap($user);
            $formatted = $this->formatArticle($article, includeBody: true);
            $formatted = $this->applyReadState($formatted, $readMap);

            return $formatted;
        }

        return null;
    }

    public function markArticleRead(User $user, string $slug, ?Empresa $empresa): bool
    {
        $key = $this->articleKeyFromSlug($slug);
        if ($key === '') {
            return false;
        }

        $this->readService->markRead($user, $key, $empresa);

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildAllItems(User $user, ?Empresa $empresa, string $layout): array
    {
        $raw = [];

        $live = $this->buildLiveDigest($user, $empresa, $layout);
        if ($live !== null) {
            $raw[] = $live;
        }

        foreach ($this->intelligence->buildInsights($user, $empresa, $layout) as $insight) {
            $raw[] = $insight;
        }

        foreach ($this->loadCatalog() as $article) {
            if (!$this->isVisible($user, $layout, $article)) {
                continue;
            }
            $raw[] = $article;
        }

        return $raw;
    }

    /** @return list<array<string, mixed>> */
    private function loadCatalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $path = $this->projectDir . '/config/welcome_news.json';
        if (!is_readable($path)) {
            $this->catalog = [];

            return $this->catalog;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->catalog = \is_array($data['articles'] ?? null) ? $data['articles'] : [];
        } catch (\Throwable) {
            $this->catalog = [];
        }

        return $this->catalog;
    }

    /** @param array<string, mixed> $article */
    private function isVisible(User $user, string $layout, array $article): bool
    {
        $layouts = $article['layouts'] ?? null;
        if (\is_array($layouts) && $layouts !== [] && !\in_array($layout, $layouts, true)) {
            return false;
        }

        if (isset($article['check']) && !$this->canShowByCheck($user, (string) $article['check'])) {
            return false;
        }

        return true;
    }

    private function canShowByCheck(User $user, string $check): bool
    {
        return match ($check) {
            'modulo_engenharia' => $this->navigation->showModuloEngenharia($user),
            'hub_operacoes' => $this->navigation->showHubOperacoes($user),
            'hub_talentos' => $this->navigation->showHubTalentos($user),
            'hub_maturidade' => $this->navigation->showHubMaturidade($user),
            default => true,
        };
    }

    /** @return ?array<string, mixed> */
    private function buildLiveDigest(User $user, ?Empresa $empresa, string $layout): ?array
    {
        if ($empresa === null) {
            return null;
        }

        $today = new DateTimeImmutable('now', new DateTimeZone(self::TZ));
        $kpis = $this->dashboardStats->getKpis($user, $empresa, $layout, 1);
        if ($kpis === []) {
            return null;
        }

        $highlights = \array_slice($kpis, 0, 3);
        $lines = [];
        foreach ($highlights as $kpi) {
            $lines[] = sprintf(
                '%s: %s registrado(s) nesta área de trabalho.',
                $kpi['label'],
                number_format((int) $kpi['value'], 0, ',', '.'),
            );
        }

        $empresaNome = $empresa->getNome() ?? 'sua empresa';
        $slug = 'pulso-da-area-' . $today->format('Y-m-d');

        return [
            'id' => 'live-digest',
            'slug' => $slug,
            'category' => 'Atualização ao vivo',
            'title' => 'Pulso de ' . $empresaNome . ' — ' . $today->format('d/m/Y'),
            'summary' => 'Resumo gerado agora com os indicadores reais cadastrados na plataforma.',
            'icon' => 'fa-bolt',
            'published_at' => $today->format('Y-m-d'),
            'published_ts' => $today->getTimestamp(),
            'read_min' => 2,
            'is_live' => true,
            'is_new' => true,
            'related_route' => 'app_dashboard',
            'body' => array_merge(
                [
                    'Este briefing é montado automaticamente a cada acesso, usando dados já existentes no banco da sua área de trabalho. Não é um relatório estático: reflete o momento atual da operação.',
                ],
                $lines,
                [
                    'Use os números acima para priorizar o dia: admissões pendentes, projetos ativos ou expansão de quadro. Acesse o dashboard para detalhar cada indicador.',
                    'Volte amanhã — o pulso será recalculado com a foto mais recente da sua empresa.',
                ],
            ),
        ];
    }

    /**
     * @param array<string, true> $readMap
     *
     * @return list<array<string, mixed>>
     */
    private function buildFeedItems(User $user, ?Empresa $empresa, string $layout, array $readMap): array
    {
        $raw = $this->buildAllItems($user, $empresa, $layout);
        $items = [];
        foreach ($raw as $article) {
            $items[] = $this->applyReadState($this->formatListItem($article), $readMap);
        }

        return $this->applyWeeklyRotation($this->sortFeed($items));
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function filterItemsByReadState(array $items, string $filter): array
    {
        return array_values(array_filter($items, static function (array $item) use ($filter): bool {
            $isRead = !empty($item['is_read']);

            return $filter === self::FILTER_READ ? $isRead : !$isRead;
        }));
    }

    /**
     * @param array<string, mixed> $article
     * @param array<string, true> $readMap
     *
     * @return array<string, mixed>
     */
    private function applyReadState(array $article, array $readMap): array
    {
        $key = $this->articleKeyFromSlug((string) ($article['slug'] ?? ''));
        $isRead = isset($readMap[$key]);

        $article['article_key'] = $key;
        $article['is_read'] = $isRead;

        if ($isRead) {
            $article['is_new'] = false;
        }

        if (!empty($article['is_insight']) && !$isRead) {
            $article['is_new'] = true;
        }

        return $article;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function sortFeed(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            $aUnread = empty($a['is_read']) ? 0 : 1;
            $bUnread = empty($b['is_read']) ? 0 : 1;
            if ($aUnread !== $bUnread) {
                return $aUnread <=> $bUnread;
            }

            if (!empty($a['is_live']) !== !empty($b['is_live'])) {
                return !empty($b['is_live']) <=> !empty($a['is_live']);
            }

            return ($b['published_ts'] ?? 0) <=> ($a['published_ts'] ?? 0);
        });

        return $items;
    }

    /**
     * @param array<string, mixed> $article
     *
     * @return array<string, mixed>
     */
    private function formatListItem(array $article): array
    {
        $formatted = $this->formatArticle($article, includeBody: false);
        $formatted['route'] = 'app_welcome_news_show';
        $formatted['is_insight'] = !empty($article['is_insight']);

        return $formatted;
    }

    /**
     * @param array<string, mixed> $article
     *
     * @return array<string, mixed>
     */
    private function formatArticle(array $article, bool $includeBody): array
    {
        $publishedAt = (string) ($article['published_at'] ?? date('Y-m-d'));
        $ts = strtotime($publishedAt) ?: time();
        $isNew = (time() - $ts) <= (self::NEW_DAYS * 86400)
            || !empty($article['is_live'])
            || !empty($article['is_insight']);

        $out = [
            'id' => (string) ($article['id'] ?? ''),
            'slug' => (string) ($article['slug'] ?? $article['id'] ?? ''),
            'category' => (string) ($article['category'] ?? 'Geral'),
            'title' => (string) ($article['title'] ?? ''),
            'summary' => (string) ($article['summary'] ?? ''),
            'icon' => (string) ($article['icon'] ?? 'fa-newspaper'),
            'date_label' => $this->formatDateLabel($publishedAt),
            'published_at' => $publishedAt,
            'published_ts' => $ts,
            'read_min' => (int) ($article['read_min'] ?? 3),
            'is_new' => $isNew,
            'is_live' => !empty($article['is_live']),
            'is_insight' => !empty($article['is_insight']),
            'related_route' => $article['related_route'] ?? null,
        ];

        if ($includeBody) {
            $body = $article['body'] ?? [];
            $out['body'] = \is_array($body) ? array_values(array_map('strval', $body)) : [];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function applyWeeklyRotation(array $items): array
    {
        if (\count($items) < 2) {
            return $items;
        }

        $pinned = [];
        $rest = [];
        foreach ($items as $item) {
            if (!empty($item['is_live']) || !empty($item['is_insight'])) {
                $pinned[] = $item;
            } else {
                $rest[] = $item;
            }
        }

        if ($rest === []) {
            return $pinned;
        }

        $week = (int) (new DateTimeImmutable('now', new DateTimeZone(self::TZ)))->format('W');
        $offset = $week % \count($rest);
        if ($offset > 0) {
            $rest = array_merge(\array_slice($rest, $offset), \array_slice($rest, 0, $offset));
        }

        return array_merge($pinned, $rest);
    }

    private function formatDateLabel(string $isoDate): string
    {
        try {
            return (new DateTimeImmutable($isoDate, new DateTimeZone(self::TZ)))->format('d/m/Y');
        } catch (\Exception) {
            return $isoDate;
        }
    }

    private function articleKeyFromSlug(string $slug): string
    {
        return trim($slug);
    }
}
