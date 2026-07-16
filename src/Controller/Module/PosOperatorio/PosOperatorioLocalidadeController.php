<?php

namespace App\Controller\Module\PosOperatorio;

use App\Service\PosOperatorio\ClinicLocalidadeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/api/localidades')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioLocalidadeController extends AbstractController
{
    public function __construct(
        private ClinicLocalidadeService $localidades,
    ) {}

    #[Route('/cep/{cep}', name: 'app_pos_operatorio_localidades_cep', methods: ['GET'], requirements: ['cep' => '\d{8}|\d{5}-?\d{3}'])]
    public function cep(string $cep): JsonResponse
    {
        try {
            $data = $this->localidades->lookupCep($cep);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        if ($data === null) {
            return $this->json(['error' => 'CEP não encontrado.'], 404);
        }

        return $this->json(['ok' => true, 'endereco' => $data]);
    }

    #[Route('/cidades', name: 'app_pos_operatorio_localidades_cidades', methods: ['GET'])]
    public function cidades(Request $request): JsonResponse
    {
        $uf = $request->query->getString('uf');
        try {
            $items = $this->localidades->listCidades($uf);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json([
            'items' => array_map(
                static fn (array $c): array => [
                    'value' => $c['nome'],
                    'label' => $c['nome'],
                    'ibge' => $c['ibge'],
                ],
                $items
            ),
        ]);
    }

    #[Route('/bairros', name: 'app_pos_operatorio_localidades_bairros', methods: ['GET'])]
    public function bairros(Request $request): JsonResponse
    {
        $ibge = $request->query->getString('ibge');
        $extra = $request->query->getString('bairro');
        $items = $this->localidades->listBairros($ibge !== '' ? $ibge : null, $extra !== '' ? $extra : null);

        return $this->json([
            'items' => array_map(
                static fn (string $nome): array => ['value' => $nome, 'label' => $nome],
                $items
            ),
        ]);
    }
}
