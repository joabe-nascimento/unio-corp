<?php

namespace App\Controller\Core;

use App\Service\NotificationMockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotificationsController extends AbstractController
{
    #[Route('/notificacoes', name: 'app_notifications')]
    public function index(NotificationMockService $notifications): Response
    {
        $items = $notifications->getAll();

        return $this->render('core/notifications/index.html.twig', [
            'notifications' => $items,
            'unread_count' => count(array_filter($items, static fn (array $n): bool => !$n['read'])),
            'total_count' => count($items),
        ]);
    }
}
