<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\User;
use App\Service\PosOperatorio\ClinicOutcomesService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/outcomes')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioOutcomesController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicOutcomesService $outcomes,
    ) {}

    #[Route('', name: 'app_pos_operatorio_outcomes')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $medicoId = $request->query->getInt('medico') ?: null;

        return $this->render('modules/pos-operatorio/ops/outcomes.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'outcomes',
            'outcomes' => $this->outcomes->buildDashboard($empresa, $medicoId),
        ]);
    }

    #[Route('/export', name: 'app_pos_operatorio_outcomes_export')]
    public function export(Request $request): StreamedResponse
    {
        $empresa = $this->requireEmpresa();
        $medicoId = $request->query->getInt('medico') ?: null;

        return $this->outcomes->exportCsv($empresa, $medicoId);
    }

    private function requireEmpresa(): \App\Entity\Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw $this->createAccessDeniedException('Área de trabalho indisponível.');
        }

        return $empresa;
    }
}
