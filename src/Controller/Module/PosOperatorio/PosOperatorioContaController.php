<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicContaService;
use App\Service\PosOperatorio\ClinicConvenioService;
use App\Service\PosOperatorio\ClinicGuiaTissService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/contas')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioContaController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicContaService $contas,
        private ClinicConvenioService $convenios,
        private ClinicGuiaTissService $guias,
    ) {}

    #[Route('', name: 'app_pos_operatorio_contas', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = $request->query->getString('status', 'aberto');
        if (!\in_array($status, ['aberto', 'pago', 'cancelado', 'glosado', 'todos'], true)) {
            $status = 'aberto';
        }

        $filtro = $status === 'todos' ? null : $status;
        $contas = $this->contas->list($empresa, $filtro);
        $totalContas = $this->contas->countList($empresa, $filtro);
        $listLimit = $this->contas->listLimit();
        $guiaPorConta = [];
        foreach ($contas as $conta) {
            if ($conta->getTipo() === 'convenio' || $conta->getConvenio() !== null) {
                $guia = $this->guias->findByConta($empresa, $conta);
                if ($guia !== null) {
                    $guiaPorConta[$conta->getId()] = $guia;
                }
            }
        }

        return $this->render('modules/pos-operatorio/contas/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'contas',
            'filtro_status' => $status,
            'contas' => $contas,
            'convenios' => $this->convenios->list($empresa, true),
            'guia_por_conta' => $guiaPorConta,
            'status_labels' => ClinicContaService::statusLabels(),
            'tipo_labels' => ClinicContaService::tipoLabels(),
            'list_total' => $totalContas,
            'list_limit' => $listLimit,
            'list_truncated' => $totalContas > $listLimit,
        ]);
    }

    #[Route('/{id}/acao', name: 'app_pos_operatorio_contas_acao', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function acao(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $conta = $this->contas->findForEmpresa($empresa, $id);
        if ($conta === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_conta_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_contas');
        }

        $action = $request->request->getString('action');
        $valorRaw = trim($request->request->getString('valor'));
        $valorCentavos = null;
        if ($valorRaw !== '') {
            $normalized = str_replace(['.', ' '], ['', ''], $valorRaw);
            $normalized = str_replace(',', '.', $normalized);
            if (!is_numeric($normalized)) {
                $this->addFlash('error', 'Valor inválido.');

                return $this->redirectToRoute('app_pos_operatorio_contas', ['status' => 'aberto']);
            }
            $valorCentavos = (int) round(((float) $normalized) * 100);
        }

        try {
            match ($action) {
                'pago' => $this->contas->markPago($conta, $empresa, $valorCentavos),
                'cortesia' => $this->contas->markCortesia($conta, $empresa),
                'cancelar' => $this->contas->cancel($conta, $empresa),
                default => throw new \InvalidArgumentException('Ação inválida.'),
            };
            $this->addFlash('success', 'Conta atualizada.');

            return $this->redirectToRoute('app_pos_operatorio_contas', [
                'status' => $action === 'cancelar' ? 'cancelado' : 'pago',
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_pos_operatorio_contas', ['status' => 'aberto']);
        }
    }

    private function requireEmpresa(): Empresa
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Área de trabalho indisponível.');
        }

        return $empresa;
    }
}
