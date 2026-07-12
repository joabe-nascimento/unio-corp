<?php

namespace App\Controller\Api;

use App\Entity\ClinicApiToken;
use App\Repository\ClinicApiTokenRepository;
use App\Service\PosOperatorio\ClinicVerificacaoPublicaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/clinica/v1')]
final class ClinicPublicApiController extends AbstractController
{
    public function __construct(
        private ClinicApiTokenRepository $tokens,
        private ClinicVerificacaoPublicaService $verificacao,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/verificar/{codigo}', name: 'api_clinica_verificar', methods: ['GET'])]
    public function verificar(Request $request, string $codigo): JsonResponse
    {
        $token = $this->resolveToken($request);
        if ($token === null) {
            return $this->json(['error' => 'Token inválido'], 401);
        }
        if (!\in_array('verificar', $token->getEscopos(), true)) {
            return $this->json(['error' => 'Escopo insuficiente'], 403);
        }

        $token->setUltimoUsoEm(new \DateTimeImmutable());
        $this->em->flush();

        $resultado = $this->verificacao->verificar($codigo);

        return $this->json([
            'ok' => $resultado['status'] === 'valida',
            'resultado' => $resultado,
        ]);
    }

    private function resolveToken(Request $request): ?ClinicApiToken
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $plain = trim(substr($header, 7));
        if ($plain === '') {
            return null;
        }

        return $this->tokens->findActiveByHash(hash('sha256', $plain));
    }
}
