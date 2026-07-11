<?php

namespace App\Service\Marketing;

/**
 * Pulso público dos módulos da landing central — notícias, curtidas e comentários reais.
 */
final class StudioModulePulsoService
{
    public function __construct(
        private ClinicLandingService $landing,
        private StudioModuleEngagementStore $engagement,
        private StudioModuleNewsService $news,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function snapshot(string $moduleId, ?string $visitorId = null): ?array
    {
        $hub = $this->landing->hubById($moduleId);
        if ($hub === null) {
            return null;
        }

        $stored = $this->engagement->read($moduleId);

        return [
            'module_id' => $moduleId,
            'label' => $hub['label'],
            'desc' => $hub['desc'],
            'icon' => $hub['icon'],
            'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'news' => $this->news->headlines($moduleId),
            'kpis' => $hub['kpis'] ?? [],
            'likes' => [
                'count' => \count($stored['likes']),
                'liked' => $visitorId !== null && $visitorId !== '' && \in_array($visitorId, $stored['likes'], true),
            ],
            'comments' => $this->formatComments($stored['comments']),
        ];
    }

    public function toggleLike(string $moduleId, string $visitorId): ?array
    {
        if ($this->landing->hubById($moduleId) === null || $visitorId === '') {
            return null;
        }

        $this->engagement->toggleLike($moduleId, $visitorId);

        return $this->snapshot($moduleId, $visitorId);
    }

    /** @return array<string, mixed>|null */
    public function addComment(string $moduleId, string $visitorId, string $author, string $text): ?array
    {
        if ($this->landing->hubById($moduleId) === null || $text === '') {
            return null;
        }

        $safeAuthor = $author !== '' ? $author : 'Visitante';
        $this->engagement->addComment($moduleId, $safeAuthor, $text);

        return $this->snapshot($moduleId, $visitorId);
    }

    /** @param list<array<string, mixed>> $stored */
    /** @return list<array<string, string>> */
    private function formatComments(array $stored): array
    {
        return array_map(static function (array $row): array {
            $at = $row['at'] ?? '';
            $ago = 'agora';
            if (\is_string($at) && $at !== '') {
                try {
                    $dt = new \DateTimeImmutable($at);
                    $diff = (new \DateTimeImmutable())->getTimestamp() - $dt->getTimestamp();
                    if ($diff < 3600) {
                        $ago = 'há ' . max(1, (int) floor($diff / 60)) . ' min';
                    } elseif ($diff < 86400) {
                        $ago = 'há ' . max(1, (int) floor($diff / 3600)) . ' h';
                    } else {
                        $ago = 'há ' . max(1, (int) floor($diff / 86400)) . ' dias';
                    }
                } catch (\Exception) {
                    $ago = 'agora';
                }
            }

            return [
                'id' => (string) ($row['id'] ?? ''),
                'author' => (string) ($row['author'] ?? 'Visitante'),
                'text' => (string) ($row['text'] ?? ''),
                'ago' => $ago,
            ];
        }, array_reverse($stored));
    }
}
