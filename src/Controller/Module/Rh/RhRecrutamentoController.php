<?php

namespace App\Controller\Module\Rh;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhVagaRepository;
use App\Service\Rh\RhRecrutamentoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/recrutamento')]
#[IsGranted('ROLE_USER')]
class RhRecrutamentoController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/recrutamento/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhRecrutamentoService $recrutamento,
        private RhVagaRepository $vagaRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_recrutamento', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = (string) $request->query->get('status', '');

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_recrutamento_vaga');
                /** @var User $user */
                $user = $this->getUser();
                $this->recrutamento->createVaga(
                    $empresa,
                    (string) $request->request->get('titulo', ''),
                    (string) $request->request->get('departamento', ''),
                    (string) $request->request->get('descricao', ''),
                    $user,
                );
                $this->addFlash('success', 'Vaga criada com sucesso.');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_recrutamento');
        }

        return $this->render(self::T . 'index.html.twig', [
            'vagas' => $this->recrutamento->listVagas($empresa, $status !== '' ? $status : null),
            'filter_status' => $status,
        ]);
    }

    #[Route('/{id}/candidato', name: 'app_rh_recrutamento_candidato', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function candidato(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $vaga = $this->vagaRepo->findOneBy(['id' => $id, 'empresa' => $empresa]);
        if (!$vaga) {
            throw $this->createNotFoundException();
        }

        try {
            $this->requireCsrf($request, 'rh_recrutamento_candidato');
            /** @var User $user */
            $user = $this->getUser();
            $this->recrutamento->addCandidato(
                $vaga,
                (string) $request->request->get('nome', ''),
                (string) $request->request->get('email', ''),
                (string) $request->request->get('telefone', ''),
                $user,
            );
            $this->addFlash('success', 'Candidato adicionado.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_rh_recrutamento');
    }
}
