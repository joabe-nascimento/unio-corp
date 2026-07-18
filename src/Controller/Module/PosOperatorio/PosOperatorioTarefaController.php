<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\ClinicTarefa;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\ClinicTarefaRepository;
use App\Service\Clinic\ClinicTarefaService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/tarefas')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioTarefaController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicTarefaService $tarefas,
        private ClinicTarefaRepository $tarefaRepo,
    ) {}

    #[Route('', name: 'app_pos_operatorio_tarefas', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $redirect = $request->request->getString('redirect', 'app_pos_operatorio_tarefas');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_tarefa_nova', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');
            } else {
                $vencimento = null;
                $vencRaw = trim($request->request->getString('vencimento'));
                if ($vencRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $vencRaw)) {
                    $vencimento = new \DateTimeImmutable($vencRaw);
                }

                try {
                    $user = $this->getUser();
                    $this->tarefas->create(
                        $empresa,
                        $request->request->getString('titulo'),
                        $user instanceof User ? $user : null,
                        $vencimento,
                        $request->request->getString('descricao'),
                    );
                    $this->addFlash('success', 'Tarefa adicionada.');
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }

            return $this->redirectToRoute($redirect !== '' ? $redirect : 'app_pos_operatorio_tarefas');
        }

        return $this->render('modules/pos-operatorio/tarefas/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'tarefas',
            'items' => $this->tarefas->listPendingRows($empresa, 50),
            'pending' => $this->tarefas->countPending($empresa),
        ]);
    }

    #[Route('/{id}/concluir', name: 'app_pos_operatorio_tarefa_concluir', methods: ['POST'])]
    public function concluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $redirect = $request->request->getString('redirect', 'app_pos_operatorio_tarefas');
        $tarefa = $this->findOwned($empresa, $id);

        if (!$this->isCsrfTokenValid('clinic_tarefa_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');
        } else {
            $this->tarefas->complete($tarefa);
            $this->addFlash('success', 'Tarefa concluída.');
        }

        return $this->redirectToRoute($redirect);
    }

    private function findOwned(Empresa $empresa, int $id): ClinicTarefa
    {
        $tarefa = $this->tarefaRepo->find($id);
        if (!$tarefa instanceof ClinicTarefa
            || (int) $tarefa->getEmpresa()->getId() !== (int) $empresa->getId()
        ) {
            throw $this->createNotFoundException();
        }

        return $tarefa;
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
