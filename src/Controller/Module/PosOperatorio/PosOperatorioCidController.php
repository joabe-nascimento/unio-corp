<?php

namespace App\Controller\Module\PosOperatorio;

use App\Service\PosOperatorio\ClinicCidCatalogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/api/cid10')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioCidController extends AbstractController
{
    public function __construct(
        private ClinicCidCatalogService $cid,
    ) {}

    #[Route('', name: 'app_pos_operatorio_cid10_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = $request->query->getString('q');
        $limit = max(1, min(50, $request->query->getInt('limit', 20)));

        return $this->json([
            'items' => $this->cid->search($q, $limit),
        ]);
    }
}
