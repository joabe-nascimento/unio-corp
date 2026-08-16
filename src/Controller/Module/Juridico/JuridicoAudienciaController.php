<?php

namespace App\Controller\Module\Juridico;

use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoAudienciaRepository;
use App\Repository\UserRepository;
use App\Service\Juridico\JuridicoAudienciaService;
use App\Service\Juridico\JuridicoProcessoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/audiencias')]
#[IsGranted('ROLE_USER')]
class JuridicoAudienciaController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoAudienciaService $audiencias,
        private JuridicoAudienciaRepository $repo,
        private JuridicoProcessoService $processos,
        private UserRepository $userRepo,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_audiencias')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = (string) $request->query->get('status', '');
        $q = (string) $request->query->get('q', '');

        return $this->render('modules/juridico/audiencias_list.html.twig', [
            'audiencias' => $this->repo->findForEmpresa($empresa, $status ?: null, $q ?: null),
            'processos' => $this->processos->listForSelect($empresa),
            'responsaveis' => $this->userRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
            'metricas' => [
                'agendadas' => $this->repo->countAgendadas($empresa),
                'total' => \count($this->repo->findForEmpresa($empresa)),
            ],
            'filter_status' => $status,
            'filter_q' => $q,
            'open_novo' => $request->query->getBoolean('open_novo'),
        ]);
    }

    #[Route('/novo', name: 'app_juridico_audiencia_novo', methods: ['POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        try {
            $this->requireCsrf($request, 'juridico_audiencia_form');
            $this->audiencias->create($empresa, $request->request->all());
            $this->addFlash('success', 'Audiência agendada.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_audiencias');
    }

    #[Route('/{id}/roteiro', name: 'app_juridico_audiencia_roteiro', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function roteiro(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_audiencia_roteiro_'.$id);
        $aud = $this->audiencias->loadForEmpresa($empresa, $id);
        $this->audiencias->gerarRoteiro($aud);
        $this->addFlash('success', 'Roteiro gerado pela Sasha.');

        return $this->redirectToRoute('app_juridico_audiencias');
    }
}
