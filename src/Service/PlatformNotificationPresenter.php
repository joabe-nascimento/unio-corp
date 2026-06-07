<?php

namespace App\Service;

use App\Entity\PlatformNotificacao;

final class PlatformNotificationPresenter
{
    /** @return array{id: int, title: string, body: string, type: string, icon: string, read: bool, time_label: string, route: ?string, route_params: array<string, mixed>, open_url: string} */
    public function toView(PlatformNotificacao $n): array
    {
        return [
            'id' => (int) $n->getId(),
            'title' => $n->getTitulo(),
            'body' => $n->getMensagem(),
            'type' => $n->getSeveridade(),
            'icon' => $n->getIcon(),
            'read' => $n->isLida(),
            'time_label' => self::relativeTime($n->getCriadoEm()),
            'route' => $n->getRouteName(),
            'route_params' => $n->getRouteParams() ?? [],
            'open_url' => 'app_notifications_open',
        ];
    }

    public static function relativeTime(\DateTimeImmutable $at): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $at->getTimestamp();

        if ($diff < 60) {
            return 'Agora';
        }
        if ($diff < 3600) {
            $min = (int) floor($diff / 60);

            return 'Há ' . $min . ' min';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);

            return 'Há ' . $h . ' h';
        }
        if ($diff < 172800) {
            return 'Ontem';
        }
        if ($diff < 604800) {
            $d = (int) floor($diff / 86400);

            return 'Há ' . $d . ' dias';
        }

        return $at->format('d/m/Y');
    }
}
