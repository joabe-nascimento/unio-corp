<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\User;
use App\Service\PosOperatorio\PosOperatorioAlertQueueService;
use App\Service\PosOperatorio\PosOperatorioMercureTopics;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/api')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioApiController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private PosOperatorioAlertQueueService $queue,
        private PosOperatorioMercureTopics $topics,
        private HubRegistry $hubRegistry,
        private Authorization $authorization,
    ) {}

    #[Route('/mercure/subscribe', name: 'app_pos_operatorio_api_mercure', methods: ['GET'])]
    public function mercureSubscribe(Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $topics = [$this->topics->alertas((int) $empresa->getId())];
        $hubUrl = $this->hubRegistry->getHub()->getPublicUrl();
        $separator = str_contains($hubUrl, '?') ? '&' : '?';
        foreach ($topics as $topic) {
            $hubUrl .= $separator . 'topic=' . rawurlencode($topic);
            $separator = '&';
        }

        $response = $this->json(['hub_url' => $hubUrl, 'topics' => $topics]);
        $response->headers->setCookie($this->authorization->createCookie($request, $topics));

        return $response;
    }

    #[Route('/alertas/poll', name: 'app_pos_operatorio_api_alertas_poll', methods: ['GET'])]
    public function alertasPoll(): JsonResponse
    {
        $empresa = $this->requireEmpresa();

        return $this->json($this->queue->buildPollSnapshot($empresa));
    }

    private function requireEmpresa(): \App\Entity\Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw $this->createAccessDeniedException('Selecione um workspace.');
        }

        return $empresa;
    }
}
