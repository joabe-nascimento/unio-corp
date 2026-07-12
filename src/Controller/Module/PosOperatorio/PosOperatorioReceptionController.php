<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\Clinic\ClinicDayPanelService;
use App\Service\Clinic\ClinicReceptionService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioReceptionController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicReceptionService $reception,
        private ClinicDayPanelService $dayPanel,
    ) {}

    #[Route('/recepcao', name: 'app_pos_operatorio_recepcao')]
    public function scanner(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $result = null;

        if ($request->isMethod('POST')) {
            $metodo = $request->request->getString('metodo', 'qr');
            $valor = $request->request->getString('valor');
            $result = $this->reception->checkin($empresa, $metodo, $valor, $this->getUser() instanceof User ? $this->getUser() : null);
        }

        return $this->render('modules/pos-operatorio/recepcao/scanner.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'recepcao',
            'result' => $result,
            'painel' => $this->dayPanel->build($empresa),
        ]);
    }

    #[Route('/painel-dia', name: 'app_pos_operatorio_painel_dia')]
    public function painelDia(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render('modules/pos-operatorio/ops/painel_dia.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'painel_dia',
            'painel' => $this->dayPanel->build($empresa),
        ]);
    }

    private function requireEmpresa(): Empresa
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $empresa = $this->workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Empresa não encontrada.');
        }

        return $empresa;
    }
}
