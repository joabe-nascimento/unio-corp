<?php

namespace App\Controller\Core;

use App\Entity\User;
use App\Service\PermissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/permissions/api')]
#[IsGranted('ROLE_GESTOR_EQUIPE')]
class PermissionsApiController extends AbstractController
{
    public function __construct(
        private PermissionService $permissions,
    ) {
    }

    #[Route('/member/{memberId}/grants', name: 'app_permissions_api_grants', requirements: ['memberId' => '[a-zA-Z0-9.\-]+'], methods: ['PUT'])]
    public function saveGrants(string $memberId, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('perm_grants', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['error' => 'Token CSRF inválido.'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Não autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || !isset($payload['grants']) || !\is_array($payload['grants'])) {
            return $this->json(['error' => 'Payload inválido.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var array<string, string> $grants */
        $grants = $payload['grants'];

        try {
            $count = $this->permissions->saveMemberGrants($memberId, $grants, $user);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'ok' => true,
            'saved' => $count,
            'grant_count' => \count(array_filter($grants)),
        ]);
    }
}
