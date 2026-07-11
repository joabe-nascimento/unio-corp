<?php

namespace App\Service\Marketing;

/**
 * Pulso público dos módulos da landing central — feed dinâmico, curtidas e comentários.
 */
final class StudioModulePulsoService
{
    public function __construct(
        private StudioLandingService $landing,
        private StudioModuleEngagementStore $engagement,
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
        $seedLikes = $this->seedLikeCount($moduleId);

        return [
            'module_id' => $moduleId,
            'label' => $hub['label'],
            'desc' => $hub['desc'],
            'icon' => $hub['icon'],
            'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'activities' => $this->liveActivities($hub['activities'] ?? [], $moduleId),
            'kpis' => $hub['kpis'] ?? [],
            'likes' => [
                'count' => $seedLikes + \count($stored['likes']),
                'liked' => $visitorId !== null && $visitorId !== '' && \in_array($visitorId, $stored['likes'], true),
            ],
            'comments' => $this->mergeComments($moduleId, $stored['comments']),
        ];
    }

    public function toggleLike(string $moduleId, string $visitorId): ?array
    {
        if ($this->landing->hubById($moduleId) === null || $visitorId === '') {
            return null;
        }

        $liked = $this->engagement->toggleLike($moduleId, $visitorId);

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

    /** @param list<array<string, string>> $base */
    /** @return list<array<string, string>> */
    private function liveActivities(array $base, string $moduleId): array
    {
        if ($base === []) {
            return [];
        }

        $tick = (int) floor(time() / 20);
        $offset = crc32($moduleId . ':' . $tick) % \count($base);
        $rotated = array_merge(\array_slice($base, $offset), \array_slice($base, 0, $offset));

        return array_map(function (array $item, int $index) use ($tick): array {
            $minutes = max(1, (($tick + $index * 7) % 55) + 3);
            $ago = $minutes < 60
                ? 'há ' . $minutes . ' min'
                : 'há ' . (int) floor($minutes / 60) . ' h';

            return $item + ['ago' => $ago];
        }, $rotated, array_keys($rotated));
    }

    private function seedLikeCount(string $moduleId): int
    {
        return 6 + (crc32($moduleId) % 18);
    }

    /** @param list<array<string, mixed>> $stored */
    /** @return list<array<string, string>> */
    private function mergeComments(string $moduleId, array $stored): array
    {
        $seed = $this->seedComments($moduleId);
        $live = array_map(static function (array $row): array {
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
                'live' => '1',
            ];
        }, array_reverse($stored));

        return array_merge($live, $seed);
    }

    /** @return list<array<string, string>> */
    private function seedComments(string $moduleId): array
    {
        return match ($moduleId) {
            'rh' => [
                ['id' => 'seed-rh-1', 'author' => 'Ana — RH', 'text' => 'O portal do colaborador reduziu chamados internos.', 'ago' => 'há 3 dias', 'live' => '0'],
            ],
            'engenharia' => [
                ['id' => 'seed-eng-1', 'author' => 'Tech lead', 'text' => 'Playbooks padronizaram nossos deploys.', 'ago' => 'há 2 dias', 'live' => '0'],
            ],
            'financeiro' => [
                ['id' => 'seed-fin-1', 'author' => 'Controller', 'text' => 'Trilha de aprovação ficou auditável de ponta a ponta.', 'ago' => 'há 4 dias', 'live' => '0'],
            ],
            'ti' => [
                ['id' => 'seed-ti-1', 'author' => 'Núcleo TI', 'text' => 'Fila de chamados com SLA ajudou muito o time.', 'ago' => 'há 1 dia', 'live' => '0'],
            ],
            'pos-operatorio' => [
                ['id' => 'seed-pos-1', 'author' => 'Enfermagem', 'text' => 'Alertas P2 chegam rápido no painel.', 'ago' => 'há 2 dias', 'live' => '0'],
            ],
            'operacoes' => [
                ['id' => 'seed-ops-1', 'author' => 'COO', 'text' => 'Visão entre áreas sem planilha paralela.', 'ago' => 'há 5 dias', 'live' => '0'],
            ],
            default => [],
        };
    }
}
