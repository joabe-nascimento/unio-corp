<?php

namespace App\Controller\Module\Inovacao;

use App\Entity\User;
use App\Service\InovacaoConexaoService;
use App\Service\InovacaoImpactService;
use App\Service\InovacaoNovidadeService;
use App\Service\InovacaoService;
use App\Service\InovacaoTendenciaService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inovacao/gerenciar')]
#[IsGranted('ROLE_USER')]
final class InovacaoManageController extends AbstractController
{
    use InovacaoEmpresaScopeTrait;

    private const T = 'modules/inovacao/manage/';

    public function __construct(
        private WorkspaceService $workspace,
        private InovacaoConexaoService $conexoes,
        private InovacaoTendenciaService $tendencias,
        private InovacaoNovidadeService $novidades,
        private InovacaoImpactService $impact,
        private InovacaoService $inovacao,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    // ── Conexões ─────────────────────────────────────────────────────────────

    #[Route('/conexoes/nova', name: 'app_inovacao_conexao_nova', methods: ['GET', 'POST'])]
    public function conexaoNova(Request $request): Response
    {
        return $this->handleResourceForm(
            $request,
            'conexao',
            'app_inovacao_conexoes',
            fn ($empresa, $user, $data) => $this->conexoes->createFromForm($empresa, $user, $data),
        );
    }

    #[Route('/conexoes/{id}/editar', name: 'app_inovacao_conexao_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function conexaoEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->conexoes->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'inovacao_conexao_form');
                $this->conexoes->updateFromForm($item, $request->request->all());
                $this->addFlash('success', 'Conexão atualizada.');

                return $this->redirectToRoute('app_inovacao_conexoes');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->renderManageForm('conexao', $item, $request->request->all());
    }

    #[Route('/conexoes/{id}/excluir', name: 'app_inovacao_conexao_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function conexaoExcluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->conexoes->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'inovacao_conexao_delete_' . $id);
        $this->conexoes->delete($item);
        $this->addFlash('success', 'Conexão excluída.');

        return $this->redirectToRoute('app_inovacao_conexoes');
    }

    // ── Tendências ───────────────────────────────────────────────────────────

    #[Route('/tendencias/nova', name: 'app_inovacao_tendencia_nova', methods: ['GET', 'POST'])]
    public function tendenciaNova(Request $request): Response
    {
        return $this->handleResourceForm(
            $request,
            'tendencia',
            'app_inovacao_tendencias',
            fn ($empresa, $user, $data) => $this->tendencias->createFromForm($empresa, $data),
        );
    }

    #[Route('/tendencias/{id}/editar', name: 'app_inovacao_tendencia_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function tendenciaEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->tendencias->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'inovacao_tendencia_form');
                $this->tendencias->updateFromForm($item, $request->request->all());
                $this->addFlash('success', 'Tendência atualizada.');

                return $this->redirectToRoute('app_inovacao_tendencias');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->renderManageForm('tendencia', $item, $request->request->all());
    }

    #[Route('/tendencias/{id}/excluir', name: 'app_inovacao_tendencia_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function tendenciaExcluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->tendencias->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'inovacao_tendencia_delete_' . $id);
        $this->tendencias->delete($item);
        $this->addFlash('success', 'Tendência excluída.');

        return $this->redirectToRoute('app_inovacao_tendencias');
    }

    // ── Novidades ────────────────────────────────────────────────────────────

    #[Route('/novidades/nova', name: 'app_inovacao_novidade_nova', methods: ['GET', 'POST'])]
    public function novidadeNova(Request $request): Response
    {
        return $this->handleResourceForm(
            $request,
            'novidade',
            'app_inovacao_novidades',
            fn ($empresa, $user, $data) => $this->novidades->createFromForm($empresa, $user, $data),
        );
    }

    #[Route('/novidades/{id}/editar', name: 'app_inovacao_novidade_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function novidadeEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->novidades->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'inovacao_novidade_form');
                $this->novidades->updateFromForm($item, $request->request->all());
                $this->addFlash('success', 'Novidade atualizada.');

                return $this->redirectToRoute('app_inovacao_novidades');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->renderManageForm('novidade', $item, $request->request->all());
    }

    #[Route('/novidades/{id}/excluir', name: 'app_inovacao_novidade_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function novidadeExcluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->novidades->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'inovacao_novidade_delete_' . $id);
        $this->novidades->delete($item);
        $this->addFlash('success', 'Novidade excluída.');

        return $this->redirectToRoute('app_inovacao_novidades');
    }

    // ── Impact Ledger ────────────────────────────────────────────────────────

    #[Route('/impact/nova', name: 'app_inovacao_impact_nova', methods: ['GET', 'POST'])]
    public function impactNova(Request $request): Response
    {
        return $this->handleResourceForm(
            $request,
            'impact',
            'app_inovacao_impact',
            fn ($empresa, $user, $data) => $this->impact->createFromForm($empresa, $data),
        );
    }

    #[Route('/impact/{id}/editar', name: 'app_inovacao_impact_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function impactEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->impact->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'inovacao_impact_form');
                $this->impact->updateFromForm($item, $request->request->all());
                $this->addFlash('success', 'Entrada de impacto atualizada.');

                return $this->redirectToRoute('app_inovacao_impact');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->renderManageForm('impact', $item, $request->request->all());
    }

    #[Route('/impact/{id}/excluir', name: 'app_inovacao_impact_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function impactExcluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->impact->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'inovacao_impact_delete_' . $id);
        $this->impact->delete($item);
        $this->addFlash('success', 'Entrada de impacto excluída.');

        return $this->redirectToRoute('app_inovacao_impact');
    }

    /**
     * @param callable(\App\Entity\Empresa, User, array<string, mixed>): object $create
     */
    private function handleResourceForm(
        Request $request,
        string $type,
        string $redirectRoute,
        callable $create,
    ): Response {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'inovacao_' . $type . '_form');
                $create($empresa, $user, $request->request->all());
                $this->addFlash('success', 'Registro criado.');

                return $this->redirectToRoute($redirectRoute);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->renderManageForm($type, null, $request->request->all());
    }

    /** @param array<string, mixed> $formData */
    private function renderManageForm(string $type, ?object $item, array $formData): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $base = $this->inovacao->getDashboard($user);

        return $this->render(self::T . $type . '_form.html.twig', array_merge($base, [
            'item' => $item,
            'form_data' => $formData,
            'manage_type' => $type,
        ]));
    }
}
