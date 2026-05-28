<?php

namespace App\Controller\Module\Cortex;

use App\Service\CortexIntelligenceService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cortex')]
#[IsGranted('ROLE_USER')]
final class CortexController extends AbstractController
{
    #[Route('', name: 'app_cortex')]
    public function index(
        CortexIntelligenceService $cortex,
        WorkspaceService $workspace,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $empresa = $workspace->getActiveEmpresa($user);
        $payload = $cortex->buildPayload($user, $empresa);
        $payload = $this->enrichPayloadUrls($payload);

        return $this->render('modules/cortex/index.html.twig', [
            'empresa' => $empresa,
            'cortex_payload' => $payload,
            'cortex_api_url' => $this->generateUrl('app_cortex_api_payload'),
        ]);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function enrichPayloadUrls(array $payload): array
    {
        foreach ($payload['graph']['nodes'] ?? [] as $idx => $node) {
            if (!empty($node['route'])) {
                try {
                    $payload['graph']['nodes'][$idx]['url'] = $this->generateUrl(
                        $node['route'],
                        $node['routeParams'] ?? [],
                        UrlGeneratorInterface::ABSOLUTE_PATH,
                    );
                } catch (\Throwable) {
                    unset($payload['graph']['nodes'][$idx]['url'], $payload['graph']['nodes'][$idx]['route']);
                }
            }
        }

        foreach ($payload['insights'] ?? [] as $idx => $insight) {
            $route = $insight['route'] ?? null;
            if (!\is_string($route) || $route === '') {
                $payload['insights'][$idx]['route'] = null;
                continue;
            }
            try {
                $this->generateUrl($route);
            } catch (\Throwable) {
                $payload['insights'][$idx]['route'] = null;
            }
        }

        return $payload;
    }
}
