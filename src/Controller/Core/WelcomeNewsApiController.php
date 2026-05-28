<?php

namespace App\Controller\Core;

use App\Service\NavigationService;
use App\Service\WelcomeNewsFeedService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/bem-vindo/api/noticias')]
final class WelcomeNewsApiController extends AbstractController
{
    #[Route('', name: 'app_welcome_news_api_feed', methods: ['GET'])]
    public function feed(
        WelcomeNewsFeedService $newsFeed,
        WorkspaceService $workspace,
        NavigationService $navigation,
        Request $request,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $empresa = $workspace->getActiveEmpresa($user);
        $layout = $navigation->getLayout($user);
        $limit = min(12, max(1, $request->query->getInt('limit', WelcomeNewsFeedService::FEED_PAGE_SIZE)));
        $filter = $request->query->get('filter', WelcomeNewsFeedService::FILTER_UNREAD);
        if (!\in_array($filter, [WelcomeNewsFeedService::FILTER_UNREAD, WelcomeNewsFeedService::FILTER_READ], true)) {
            $filter = WelcomeNewsFeedService::FILTER_UNREAD;
        }
        $discover = $request->query->getBoolean('discover', true);

        $payload = $newsFeed->getFeedPayloadForUser($user, $empresa, $layout, $limit, $filter, $discover);

        return $this->json([
            'items' => $payload['items'],
            'meta' => [
                'unread_count' => $payload['unread_count'],
                'read_count' => $payload['read_count'],
                'read_recent_count' => $payload['read_recent_count'],
                'total' => $payload['total'],
                'filter' => $payload['filter'],
                'refreshed' => $payload['refreshed'],
                'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }

    #[Route('/{slug}/leitura', name: 'app_welcome_news_api_read', requirements: ['slug' => '[a-z0-9\-]+'], methods: ['POST'])]
    public function markRead(
        string $slug,
        WelcomeNewsFeedService $newsFeed,
        WorkspaceService $workspace,
        NavigationService $navigation,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $empresa = $workspace->getActiveEmpresa($user);
        $layout = $navigation->getLayout($user);

        $article = $newsFeed->findArticleForUser($user, $slug, $layout, $empresa);
        if ($article === null) {
            return $this->json(['error' => 'Artigo não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $newsFeed->markArticleRead($user, $slug, $empresa);

        return $this->json([
            'ok' => true,
            'slug' => $slug,
            'read_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
