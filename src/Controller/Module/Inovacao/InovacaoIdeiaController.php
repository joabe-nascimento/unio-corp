<?php

namespace App\Controller\Module\Inovacao;

use App\Entity\InovIdeia;
use App\Entity\User;
use App\Service\InovacaoIdeiaService;
use App\Service\InovacaoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inovacao/ideias')]
#[IsGranted('ROLE_USER')]
final class InovacaoIdeiaController extends AbstractController
{
    use InovacaoEmpresaScopeTrait;

    private const T = 'modules/inovacao/';

    public function __construct(
        private WorkspaceService $workspace,
        private InovacaoIdeiaService $ideias,
        private InovacaoService $inovacao,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('/{id}', name: 'app_inovacao_ideia_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $ideia = $this->ideias->loadForEmpresa($empresa, $id);
        /** @var User $user */
        $user = $this->getUser();
        $base = $this->inovacao->getSection('backlog', $user);

        return $this->render(self::T . 'ideia_show.html.twig', array_merge($base, [
            'ideia' => $ideia,
            'pipeline_item' => $this->ideias->toPipelineArray($ideia),
            'stage_options' => $this->stageOptions(),
        ]));
    }

    #[Route('/{id}/editar', name: 'app_inovacao_ideia_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ideia = $this->ideias->loadForEmpresa($empresa, $id);
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'inovacao_ideia_form');
                $this->ideias->updateFromForm($ideia, $request->request->all());
                $this->addFlash('success', 'Ideia atualizada.');

                return $this->redirectToRoute('app_inovacao_ideia_show', ['id' => $id]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $base = $this->inovacao->getSection('nova_ideia', $user);

        return $this->render(self::T . 'ideia_form.html.twig', array_merge($base, [
            'ideia' => $ideia,
            'stage_options' => $this->stageOptions(),
            'form_data' => $request->request->all(),
        ]));
    }

    #[Route('/{id}/excluir', name: 'app_inovacao_ideia_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ideia = $this->ideias->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'inovacao_ideia_delete_' . $id);
        $this->ideias->delete($ideia);
        $this->addFlash('success', 'Ideia excluída.');

        return $this->redirectToRoute('app_inovacao_backlog');
    }

    #[Route('/{id}/promover', name: 'app_inovacao_ideia_promover', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promover(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ideia = $this->ideias->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'inovacao_ideia_action_' . $id);

        try {
            $this->ideias->promoteToLab($ideia);
            $this->addFlash('success', 'Ideia promovida ao Laboratório.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_inovacao_backlog'));
    }

    #[Route('/{id}/votar', name: 'app_inovacao_ideia_votar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function votar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ideia = $this->ideias->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'inovacao_ideia_action_' . $id);
        $this->ideias->vote($ideia);
        $this->addFlash('success', 'Voto registrado.');

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_inovacao_backlog'));
    }

    #[Route('/{id}/mover', name: 'app_inovacao_ideia_mover', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function mover(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ideia = $this->ideias->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'inovacao_ideia_action_' . $id);

        try {
            $stage = (string) $request->request->get('stage', '');
            $this->ideias->moveStage($ideia, $stage);
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => true, 'stage' => $stage]);
            }
            $this->addFlash('success', 'Estágio atualizado.');
        } catch (\InvalidArgumentException $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
            }
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_inovacao_pipeline'));
    }

    #[Route('/{id}/arquivar', name: 'app_inovacao_ideia_arquivar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function arquivar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ideia = $this->ideias->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'inovacao_ideia_action_' . $id);
        $this->ideias->archive($ideia);
        $this->addFlash('success', 'Ideia arquivada.');

        return $this->redirectToRoute('app_inovacao_backlog');
    }

    #[Route('/{id}/decisao', name: 'app_inovacao_ideia_decisao', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function decisao(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ideia = $this->ideias->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'inovacao_ideia_action_' . $id);
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->ideias->registerDecision(
                $ideia,
                $user,
                (string) $request->request->get('tipo', ''),
                (string) $request->request->get('motivo', ''),
            );
            $this->addFlash('success', 'Decisão registrada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_inovacao_experimentos'));
    }

    /** @return list<array{id: string, label: string}> */
    private function stageOptions(): array
    {
        return [
            ['id' => InovIdeia::STAGE_IDEIA, 'label' => 'Ideia'],
            ['id' => InovIdeia::STAGE_HIPOTESE, 'label' => 'Hipótese'],
            ['id' => InovIdeia::STAGE_POC, 'label' => 'POC'],
            ['id' => InovIdeia::STAGE_PILOTO, 'label' => 'Piloto'],
            ['id' => InovIdeia::STAGE_ESCALA, 'label' => 'Escala'],
            ['id' => InovIdeia::STAGE_KILL, 'label' => 'Kill'],
            ['id' => InovIdeia::STAGE_ARQUIVADO, 'label' => 'Arquivado'],
        ];
    }
}
