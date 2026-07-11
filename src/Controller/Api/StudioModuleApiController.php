<?php

namespace App\Controller\Api;

use App\Service\Marketing\StudioModulePulsoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/modulo')]
final class StudioModuleApiController extends AbstractController
{
    public function __construct(
        private StudioModulePulsoService $pulso,
    ) {
    }

    #[Route('/{id}/pulso', name: 'api_marketing_modulo_pulso', methods: ['GET'])]
    public function pulso(string $id, Request $request): JsonResponse
    {
        $snapshot = $this->pulso->snapshot($id, $this->visitorId($request));
        if ($snapshot === null) {
            return $this->json(['error' => 'Módulo não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($snapshot);
    }

    #[Route('/{id}/curtir', name: 'api_marketing_modulo_curtir', methods: ['POST'])]
    public function curtir(string $id, Request $request): JsonResponse
    {
        $visitorId = $this->visitorId($request);
        if ($visitorId === '') {
            return $this->json(['error' => 'Identificador do visitante ausente'], Response::HTTP_BAD_REQUEST);
        }

        $snapshot = $this->pulso->toggleLike($id, $visitorId);
        if ($snapshot === null) {
            return $this->json(['error' => 'Módulo não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($snapshot);
    }

    #[Route('/{id}/comentario', name: 'api_marketing_modulo_comentario', methods: ['POST'])]
    public function comentario(string $id, Request $request): JsonResponse
    {
        $visitorId = $this->visitorId($request);
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            $payload = $request->request->all();
        }

        $author = trim((string) ($payload['author'] ?? ''));
        $text = trim((string) ($payload['text'] ?? ''));
        if ($text === '') {
            return $this->json(['error' => 'Comentário vazio'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($text) > 500) {
            return $this->json(['error' => 'Comentário muito longo'], Response::HTTP_BAD_REQUEST);
        }

        $snapshot = $this->pulso->addComment($id, $visitorId, $author, $text);
        if ($snapshot === null) {
            return $this->json(['error' => 'Módulo não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($snapshot, Response::HTTP_CREATED);
    }

    private function visitorId(Request $request): string
    {
        $header = trim((string) $request->headers->get('X-Visitor-Id', ''));
        if ($header !== '' && preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $header)) {
            return $header;
        }

        $payload = json_decode($request->getContent(), true);
        if (\is_array($payload)) {
            $body = trim((string) ($payload['visitor_id'] ?? ''));
            if ($body !== '' && preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $body)) {
                return $body;
            }
        }

        $form = trim((string) $request->request->get('visitor_id', ''));

        return $form !== '' && preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $form) ? $form : '';
    }
}
