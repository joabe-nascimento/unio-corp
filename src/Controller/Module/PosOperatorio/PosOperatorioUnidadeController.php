<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicUnidadeService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/unidades')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioUnidadeController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicUnidadeService $unidades,
    ) {}

    #[Route('', name: 'app_pos_operatorio_unidades', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_unidade_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_unidades');
            }

            try {
                $this->unidades->create($empresa, [
                    'nome' => $request->request->getString('nome'),
                    'codigo' => $request->request->getString('codigo'),
                    'endereco' => $request->request->getString('endereco'),
                    'telefone' => $request->request->getString('telefone'),
                    'ativo' => true,
                ]);
                $this->addFlash('success', 'Unidade cadastrada.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_unidades');
        }

        return $this->render('modules/pos-operatorio/unidades/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'unidades',
            'unidades' => $this->unidades->list($empresa),
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_unidades_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $unidade = $this->unidades->findForEmpresa($empresa, $id);
        if ($unidade === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_unidade_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_unidades');
        }

        try {
            $this->unidades->update($unidade, $empresa, [
                'nome' => $request->request->getString('nome'),
                'codigo' => $request->request->getString('codigo'),
                'endereco' => $request->request->getString('endereco'),
                'telefone' => $request->request->getString('telefone'),
                'ativo' => $request->request->getBoolean('ativo'),
            ]);
            $this->addFlash('success', 'Unidade atualizada.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_unidades');
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
