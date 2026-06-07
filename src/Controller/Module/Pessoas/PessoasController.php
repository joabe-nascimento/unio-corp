<?php

namespace App\Controller\Module\Pessoas;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Service\FuncionarioService;
use App\Support\PessoasCargoCatalog;
use App\Service\Pessoas\PessoasAvaliacaoService;
use App\Service\Pessoas\PessoasCargoService;
use App\Service\Pessoas\PessoasDashboardService;
use App\Service\Pessoas\PessoasDepartamentoService;
use App\Service\Rh\RhOrganogramaService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pessoas')]
#[IsGranted('ROLE_USER')]
class PessoasController extends AbstractController
{
    use PessoasEmpresaScopeTrait;

    private const T = 'modules/pessoas/';

    public function __construct(
        private WorkspaceService $workspace,
        private FuncionarioService $funcionarios,
        private PessoasDashboardService $dashboard,
        private PessoasDepartamentoService $equipes,
        private PessoasCargoService $cargos,
        private PessoasAvaliacaoService $avaliacoes,
        private RhOrganogramaService $organograma,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_pessoas')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();
        $stats = $this->dashboard->getStats($empresa);

        return $this->render(self::T . 'index.html.twig', [
            'stats' => $stats,
        ]);
    }

    // ── Membros ─────────────────────────────────────────────────────────────

    #[Route('/membros', name: 'app_pessoas_membros')]
    public function membros(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = (string) $request->query->get('status', '');
        $q = trim((string) $request->query->get('q', ''));
        $deptId = (int) $request->query->get('equipe', 0);
        $cargo = trim((string) $request->query->get('cargo', ''));

        return $this->render(self::T . 'membros.html.twig', [
            'membros' => $this->funcionarios->findForPessoas(
                $empresa,
                $status !== '' ? $status : null,
                $q !== '' ? $q : null,
                $deptId > 0 ? $deptId : null,
                $cargo !== '' ? $cargo : null,
            ),
            'stats' => $this->dashboard->getMembrosStats($empresa),
            'departamentos' => $this->funcionarios->listDepartamentos($empresa),
            'cargos_opcoes' => $this->funcionarios->listDistinctCargos($empresa),
            'filter_status' => $status,
            'filter_q' => $q,
            'filter_equipe' => $deptId > 0 ? (string) $deptId : '',
            'filter_cargo' => $cargo,
        ]);
    }

    #[Route('/membros/novo', name: 'app_pessoas_membro_novo', methods: ['GET', 'POST'])]
    public function membroNovo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'pessoas_membro_form');
                $f = $this->funcionarios->create($empresa, $request->request->all(), $request->files->get('foto'));
                $this->addFlash('success', 'Membro cadastrado.');

                return $this->redirectToRoute('app_pessoas_membro_ficha', ['id' => $f->getId()]);
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->renderMembroForm($empresa, null, $request->request->all());
    }

    #[Route('/membros/{id}/editar', name: 'app_pessoas_membro_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function membroEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $membro = $this->funcionarios->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'pessoas_membro_form');
                $this->funcionarios->update(
                    $membro,
                    $request->request->all(),
                    $request->files->get('foto'),
                    $request->request->getBoolean('remove_foto')
                );
                $this->addFlash('success', 'Membro atualizado.');

                return $this->redirectToRoute('app_pessoas_membro_ficha', ['id' => $id]);
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->renderMembroForm($empresa, $membro, $request->request->all());
    }

    #[Route('/membros/{id}', name: 'app_pessoas_membro_ficha', requirements: ['id' => '\d+'])]
    public function membroFicha(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $membro = $this->funcionarios->loadForEmpresa($empresa, $id);

        return $this->render(self::T . 'ficha.html.twig', [
            'membro' => $membro,
            'historico' => $this->avaliacoes->buildHistorico($membro),
            'avaliacoes' => $this->avaliacoes->list($empresa, $id),
        ]);
    }

    // ── Equipes ─────────────────────────────────────────────────────────────

    #[Route('/equipes', name: 'app_pessoas_equipes')]
    public function equipes(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $q = trim((string) $request->query->get('q', ''));
        $area = trim((string) $request->query->get('area', ''));

        return $this->render(self::T . 'equipes.html.twig', [
            'equipes' => $this->equipes->list($empresa, $q !== '' ? $q : null, $area !== '' ? $area : null),
            'stats' => $this->dashboard->getEquipesStats($empresa),
            'areas' => $this->equipes->listAreas($empresa),
            'filter_q' => $q,
            'filter_area' => $area,
        ]);
    }

    #[Route('/equipes/nova', name: 'app_pessoas_equipe_nova', methods: ['GET', 'POST'])]
    public function equipeNova(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'pessoas_equipe_form');
                $equipe = $this->equipes->create($empresa, $request->request->all());
                $this->addFlash('success', 'Equipe criada.');

                return $this->redirectToRoute('app_pessoas_equipe_detalhe', ['id' => $equipe->getId()]);
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->renderEquipeForm($empresa, null, $request->request->all(), 'nova');
    }

    #[Route('/equipes/{id}/editar', name: 'app_pessoas_equipe_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function equipeEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $equipe = $this->equipes->load($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'pessoas_equipe_form');
                $this->equipes->update($equipe, $request->request->all());
                $this->addFlash('success', 'Equipe atualizada.');

                return $this->redirectToRoute('app_pessoas_equipe_detalhe', ['id' => $id]);
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->renderEquipeForm($empresa, $equipe, $request->request->all(), 'editar');
    }

    #[Route('/equipes/{id}', name: 'app_pessoas_equipe_detalhe', requirements: ['id' => '\d+'])]
    public function equipeDetalhe(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $equipe = $this->equipes->load($empresa, $id);
        $membros = $this->equipes->listMembros($equipe);

        $ativos = 0;
        $ferias = 0;
        $inativos = 0;
        foreach ($membros as $m) {
            match ($m->getStatus()) {
                'ATIVO' => ++$ativos,
                'FERIAS' => ++$ferias,
                'INATIVO' => ++$inativos,
                default => null,
            };
        }

        return $this->render(self::T . 'equipe_detalhe.html.twig', [
            'equipe' => $equipe,
            'membros' => $membros,
            'stats' => [
                'total' => \count($membros),
                'ativos' => $ativos,
                'ferias' => $ferias,
                'inativos' => $inativos,
            ],
        ]);
    }

    // ── Cargos ──────────────────────────────────────────────────────────────

    #[Route('/cargos', name: 'app_pessoas_cargos', methods: ['GET'])]
    public function cargos(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $q = trim((string) $request->query->get('q', ''));

        $lista = $this->cargos->list($empresa, $q !== '' ? $q : null);
        $counts = [];
        foreach ($lista as $cargo) {
            $counts[$cargo->getId()] = $this->cargos->countMembros($empresa, (string) $cargo->getTitulo());
        }

        return $this->render(self::T . 'cargos.html.twig', [
            'cargos' => $lista,
            'membros_por_cargo' => $counts,
            'stats' => $this->cargos->getListStats($empresa, $lista, $counts),
            'areas_sugestoes' => $this->cargos->listAreaSuggestions($empresa),
            'nivel_options' => PessoasCargoCatalog::nivelOptions(),
            'filter_q' => $q,
            'has_filters' => $q !== '',
        ]);
    }

    #[Route('/cargos/novo', name: 'app_pessoas_cargo_novo', methods: ['POST'])]
    public function cargoNovo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        try {
            $this->requireCsrf($request, 'pessoas_cargo_form');
            $this->cargos->create($empresa, $request->request->all());
            $this->addFlash('success', 'Cargo registrado.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $params = [];
        $q = trim((string) $request->request->get('redirect_q', ''));
        if ($q !== '') {
            $params['q'] = $q;
        }

        return $this->redirectToRoute('app_pessoas_cargos', $params);
    }

    #[Route('/cargos/{id}/editar', name: 'app_pessoas_cargo_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function cargoEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $cargo = $this->cargos->load($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'pessoas_cargo_form');
                $this->cargos->update($cargo, $request->request->all());
                $this->addFlash('success', 'Cargo atualizado.');

                return $this->redirectToRoute('app_pessoas_cargos');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(self::T . 'cargo_form.html.twig', [
            'cargo' => $cargo,
            'form_data' => $request->request->all(),
            'membros_count' => $this->cargos->countMembros($empresa, (string) $cargo->getTitulo()),
            'areas_sugestoes' => $this->cargos->listAreaSuggestions($empresa),
            'nivel_options' => PessoasCargoCatalog::nivelOptions(),
        ]);
    }

    // ── Organograma ─────────────────────────────────────────────────────────

    #[Route('/organograma', name: 'app_pessoas_organograma')]
    public function organograma(): Response
    {
        $empresa = $this->requireEmpresa();
        $tree = $this->organograma->buildTree($empresa);

        return $this->render(self::T . 'organograma.html.twig', [
            'tree' => $tree,
            'total_nodes' => $this->organograma->countNodes($empresa),
        ]);
    }

    // ── Avaliação ───────────────────────────────────────────────────────────

    #[Route('/avaliacao', name: 'app_pessoas_avaliacao', methods: ['GET'])]
    public function avaliacao(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'avaliacao.html.twig', [
            'avaliacoes' => $this->avaliacoes->list($empresa),
            'funcionarios' => $this->funcionarios->findForPessoas($empresa, 'ATIVO'),
            'total' => $this->dashboard->getStats($empresa)['avaliacoes'],
        ]);
    }

    #[Route('/avaliacao/nova', name: 'app_pessoas_avaliacao_nova', methods: ['POST'])]
    public function avaliacaoNova(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->requireCsrf($request, 'pessoas_avaliacao_form');
            $this->avaliacoes->create($empresa, $user, $request->request->all());
            $this->addFlash('success', 'Avaliação registrada.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pessoas_avaliacao');
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function renderMembroForm(\App\Entity\Empresa $empresa, ?\App\Entity\Funcionario $membro, array $formData): Response
    {
        return $this->render(self::T . 'membro_form.html.twig', [
            'empresa' => $empresa,
            'membro' => $membro,
            'modo' => $membro ? 'editar' : 'novo',
            'departamentos' => $this->funcionarios->listDepartamentos($empresa),
            'gestores' => $this->funcionarios->listGestores($empresa, $membro?->getId()),
            'cargos_catalogo' => $this->cargos->list($empresa),
            'form_data' => $formData,
        ]);
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function renderEquipeForm(
        \App\Entity\Empresa $empresa,
        ?\App\Entity\Departamento $equipe,
        array $formData,
        string $modo,
    ): Response {
        return $this->render(self::T . 'equipe_form.html.twig', [
            'empresa' => $empresa,
            'equipe' => $equipe,
            'modo' => $modo,
            'gestores' => $this->funcionarios->listGestores($empresa),
            'form_data' => $formData,
        ]);
    }
}
