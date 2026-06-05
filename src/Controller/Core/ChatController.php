<?php

namespace App\Controller\Core;

use App\Entity\User;
use App\Service\ChatService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    #[Route('/bate-papo', name: 'app_chat')]
    public function index(
        Request $request,
        ChatService $chat,
        WorkspaceService $workspace,
        HubRegistry $hubRegistry,
        Authorization $mercureAuthorization,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $workspace->getActiveEmpresa($user) ?? $user->getEmpresa();

        $conversations = [];
        $mercureTopics = [];
        $mercureEnabled = false;
        $mercureUrl = '';

        if ($empresa) {
            $conversations = $chat->getConversationsPayload($user, $empresa);
            $mercureTopics = $chat->getSubscribeTopics($user, $empresa);

            if ($mercureTopics !== []) {
                try {
                    $hub = $hubRegistry->getHub();
                    $mercureUrl = $hub->getPublicUrl();
                    $separator = '?';
                    foreach ($mercureTopics as $topic) {
                        $mercureUrl .= $separator.'topic='.rawurlencode($topic);
                        $separator = '&';
                    }
                    $mercureAuthorization->setCookie($request, subscribe: $mercureTopics);
                    $mercureEnabled = true;
                } catch (\Throwable) {
                    // Mercure indisponível — o front-end usa polling como fallback.
                }
            }
        }

        return $this->render('core/chat/index.html.twig', [
            'conversations' => $conversations,
            'user_initials' => $user->getInitials(),
            'user_id' => $user->getId(),
            'chat_api_base' => '/bate-papo/api',
            'chat_has_workspace' => $empresa !== null,
            'chat_mercure_enabled' => $mercureEnabled,
            'chat_mercure_url' => $mercureUrl,
        ]);
    }
}
