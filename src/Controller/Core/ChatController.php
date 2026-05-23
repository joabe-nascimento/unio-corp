<?php

namespace App\Controller\Core;

use App\Service\ChatMockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    #[Route('/papo', name: 'app_chat')]
    public function index(ChatMockService $chat): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $parts = preg_split('/\s+/', trim($user->getNome() ?? ''), 2);
        $initials = mb_strtoupper(mb_substr($parts[0] ?? 'U', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));

        return $this->render('core/chat/index.html.twig', [
            'conversations' => $chat->getConversations(),
            'user_initials' => $initials ?: 'U',
        ]);
    }
}
