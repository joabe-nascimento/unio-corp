<?php

namespace App\Service\Marketing;

/**
 * Persistência leve de curtidas e comentários públicos nos módulos da landing.
 */
final class StudioModuleEngagementStore
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    /** @return array{likes: list<string>, comments: list<array<string, string>>} */
    public function read(string $moduleId): array
    {
        $path = $this->path($moduleId);
        if (!is_file($path)) {
            return ['likes' => [], 'comments' => []];
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return ['likes' => [], 'comments' => []];
        }

        $data = json_decode($raw, true);
        if (!\is_array($data)) {
            return ['likes' => [], 'comments' => []];
        }

        return [
            'likes' => array_values(array_filter(
                $data['likes'] ?? [],
                static fn (mixed $id): bool => \is_string($id) && $id !== '',
            )),
            'comments' => array_values(array_filter(
                $data['comments'] ?? [],
                static fn (mixed $row): bool => \is_array($row),
            )),
        ];
    }

    /** @param array{likes: list<string>, comments: list<array<string, string>>} $data */
    public function write(string $moduleId, array $data): void
    {
        $dir = $this->dir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar diretório de engajamento.');
        }

        $encoded = json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
        if ($encoded === false) {
            throw new \RuntimeException('Falha ao serializar engajamento.');
        }

        file_put_contents($this->path($moduleId), $encoded, \LOCK_EX);
    }

    public function toggleLike(string $moduleId, string $visitorId): bool
    {
        $data = $this->read($moduleId);
        $liked = \in_array($visitorId, $data['likes'], true);
        if ($liked) {
            $data['likes'] = array_values(array_filter(
                $data['likes'],
                static fn (string $id): bool => $id !== $visitorId,
            ));
        } else {
            $data['likes'][] = $visitorId;
        }

        $this->write($moduleId, $data);

        return !$liked;
    }

    /** @return array<string, string> */
    public function addComment(string $moduleId, string $author, string $text): array
    {
        $data = $this->read($moduleId);
        $comment = [
            'id' => bin2hex(random_bytes(8)),
            'author' => $author,
            'text' => $text,
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
        $data['comments'][] = $comment;
        $this->write($moduleId, $data);

        return $comment;
    }

    private function dir(): string
    {
        return $this->projectDir . '/var/data/marketing_module_engagement';
    }

    private function path(string $moduleId): string
    {
        return $this->dir() . '/' . preg_replace('/[^a-z0-9_-]/', '', $moduleId) . '.json';
    }
}
