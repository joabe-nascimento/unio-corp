<?php

namespace App\Service\Core;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Eventos em tempo real do kanban (Projetos e Metas) — escopo por empresa.
 */
final class KanbanMercurePublisher
{
    public const EVENT_MOVED = 'kanban.task_moved';
    public const EVENT_DELETED = 'kanban.task_deleted';

    public function __construct(
        private HubInterface $hub,
        private ?string $mercureUrl,
        private string $topicBase,
    ) {}

    public function isEnabled(): bool
    {
        if ($this->mercureUrl === null || $this->mercureUrl === '') {
            return false;
        }

        return !str_contains($this->mercureUrl, 'example.com');
    }

    public function topicForEmpresa(int $empresaId): string
    {
        return rtrim($this->topicBase, '/') . '/core/empresa/' . $empresaId . '/kanban';
    }

    public function publishMoved(
        int $empresaId,
        int $taskId,
        string $status,
        int $ordem,
        int $actorUserId,
        int $projetoId,
    ): void {
        $this->publish($empresaId, [
            'type' => self::EVENT_MOVED,
            'taskId' => $taskId,
            'status' => $status,
            'ordem' => $ordem,
            'actorUserId' => $actorUserId,
            'projetoId' => $projetoId,
        ]);
    }

    public function publishDeleted(int $empresaId, int $taskId, int $actorUserId): void
    {
        $this->publish($empresaId, [
            'type' => self::EVENT_DELETED,
            'taskId' => $taskId,
            'actorUserId' => $actorUserId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publish(int $empresaId, array $payload): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->hub->publish(new Update(
            $this->topicForEmpresa($empresaId),
            json_encode($payload, \JSON_THROW_ON_ERROR),
            private: true,
        ));
    }
}
