<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicConvenioService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/convenios')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioConvenioController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicConvenioService $convenios,
    ) {}

    #[Route('', name: 'app_pos_operatorio_convenios', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_convenio_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_convenios');
            }

            try {
                $this->convenios->create($empresa, [
                    'nome' => $request->request->getString('nome'),
                    'registro_ans' => $request->request->getString('registro_ans'),
                    'ativo' => true,
                ]);
                $this->addFlash('success', 'Convênio cadastrado.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_convenios');
        }

        return $this->render('modules/pos-operatorio/convenios/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'convenios',
            'convenios' => $this->convenios->list($empresa),
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_convenios_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $convenio = $this->convenios->findForEmpresa($empresa, $id);
        if ($convenio === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_convenio_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_convenios');
        }

        try {
            $this->convenios->update($convenio, $empresa, [
                'nome' => $request->request->getString('nome'),
                'registro_ans' => $request->request->getString('registro_ans'),
                'ativo' => $request->request->getBoolean('ativo'),
            ]);
            $this->addFlash('success', 'Convênio atualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_convenios');
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
