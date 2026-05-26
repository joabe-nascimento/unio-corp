<?php

namespace App\Controller\Core;

use App\Entity\User;
use App\Service\Core\KanbanMercurePublisher;
use App\Service\NavigationService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/core/projetos')]
#[IsGranted('ROLE_USER')]
final class KanbanMercureController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private NavigationService $navigation,
        private KanbanMercurePublisher $publisher,
        private HubRegistry $hubRegistry,
        private ?string $mercurePublicUrl,
    ) {}

    #[Route('/mercure-token', name: 'app_core_kanban_mercure_token', methods: ['GET'])]
    public function token(): Response
    {
        $this->denyUnlessAllowed();

        if (!$this->publisher->isEnabled() || $this->mercurePublicUrl === null || $this->mercurePublicUrl === '') {
            return new JsonResponse(['enabled' => false]);
        }

        $empresa = $this->workspace->getActiveEmpresa($this->getUser());
        if (!$empresa) {
            return new JsonResponse(['enabled' => false], 403);
        }

        $topic = $this->publisher->topicForEmpresa($empresa->getId());
        $factory = $this->hubRegistry->getHub()->getFactory();
        if ($factory === null) {
            return new JsonResponse(['enabled' => false, 'error' => 'no_token_factory'], 503);
        }

        return new JsonResponse([
            'enabled' => true,
            'hub' => $this->mercurePublicUrl,
            'topic' => $topic,
            'token' => $factory->create(subscribe: [$topic]),
        ]);
    }

    private function denyUnlessAllowed(): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->navigation->showProjetosMetas($user)) {
            throw $this->createAccessDeniedException('Projetos e Metas não disponível para seu perfil.');
        }
    }
}
