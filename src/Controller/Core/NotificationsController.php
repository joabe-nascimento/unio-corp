<?php

namespace App\Controller\Core;

use App\Entity\User;
use App\Service\PlatformNotificationService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotificationsController extends AbstractController
{
    #[Route('/notificacoes', name: 'app_notifications', methods: ['GET'])]
    public function index(
        Request $request,
        PlatformNotificationService $notifications,
        WorkspaceService $workspace,
    ): Response {
        $user = $this->requireUser();
        $empresa = $workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            return $this->redirectToRoute('app_workspace_select');
        }

        $filtro = $request->query->getString('filtro', 'todas');
        $unreadOnly = $filtro === 'nao_lidas';
        $items = $notifications->listForUser($empresa, $user, $unreadOnly ? true : null);
        $unreadCount = $notifications->countUnread($empresa, $user);

        return $this->render('core/notifications/index.html.twig', [
            'notifications' => $items,
            'unread_count' => $unreadCount,
            'total_count' => \count($notifications->listForUser($empresa, $user)),
            'filtro' => $filtro,
        ]);
    }

    #[Route('/notificacoes/{id}/abrir', name: 'app_notifications_open', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function open(
        int $id,
        PlatformNotificationService $notifications,
        WorkspaceService $workspace,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $user = $this->requireUser();
        $empresa = $workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            return $this->redirectToRoute('app_workspace_select');
        }

        $notification = $notifications->findOwned($empresa, $user, $id);
        if ($notification === null) {
            throw $this->createNotFoundException('Notificação não encontrada.');
        }

        $notifications->markRead($empresa, $user, $id);

        $routeName = $notification->getRouteName();
        if ($routeName !== null && $routeName !== '') {
            return $this->redirect($urlGenerator->generate($routeName, $notification->getRouteParams() ?? []));
        }

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/notificacoes/marcar-todas', name: 'app_notifications_mark_all', methods: ['POST'])]
    public function markAll(
        Request $request,
        PlatformNotificationService $notifications,
        WorkspaceService $workspace,
    ): Response {
        $user = $this->requireUser();
        $empresa = $workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            return $this->redirectToRoute('app_workspace_select');
        }

        if (!$this->isCsrfTokenValid('notifications_mark_all', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        $notifications->markAllRead($empresa, $user);

        return $this->redirectToRoute('app_notifications');
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
