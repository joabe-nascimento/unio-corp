<?php

namespace App\Controller\Module\Rh;

use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Service\RhFolhaService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/folha')]
#[IsGranted('ROLE_USER')]
class RhFolhaController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhFolhaService $folha,
        private FuncionarioRepository $funcionarioRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_folha')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'folha.html.twig', [
            'competencias' => $this->folha->listForEmpresa($empresa),
            'referencia_sugerida' => $this->folha->competenciaAtualLabel(),
        ]);
    }

    #[Route('/gerar', name: 'app_rh_folha_gerar', methods: ['POST'])]
    public function gerar(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        try {
            $this->requireCsrf($request, 'rh_folha_form');
            $ref = (string) $request->request->get('referencia', $this->folha->competenciaAtualLabel());
            $comp = $this->folha->gerarCompetencia($empresa, $ref);
            $this->addFlash('success', 'Folha gerada para ' . $comp->getReferenciaLabel() . '.');

            return $this->redirectToRoute('app_rh_folha_show', ['id' => $comp->getId()]);
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_rh_folha');
    }

    #[Route('/{id}', name: 'app_rh_folha_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $comp = $this->folha->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_folha_action');
                $action = $request->request->get('action');
                if ($action === 'fechar') {
                    $this->folha->fecharCompetencia($comp);
                    $this->addFlash('success', 'Competência fechada.');
                } elseif ($action === 'lancamento') {
                    $fid = (int) $request->request->get('funcionario_id', 0);
                    $func = $fid > 0 ? $this->funcionarioRepo->findOneBy(['id' => $fid, 'empresa' => $empresa]) : null;
                    if (!$func) {
                        throw new RhProcessException('Selecione um funcionário.');
                    }
                    $this->folha->adicionarLancamento(
                        $comp,
                        $func,
                        (string) $request->request->get('tipo'),
                        (string) $request->request->get('codigo'),
                        (string) $request->request->get('descricao'),
                        (string) $request->request->get('valor'),
                    );
                    $this->addFlash('success', 'Lançamento adicionado.');
                } else {
                    throw new RhProcessException('Ação inválida.');
                }
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_folha_show', ['id' => $id]);
        }

        return $this->render(self::T . 'folha_show.html.twig', [
            'competencia' => $comp,
            'resumo' => $this->folha->resumoPorFuncionario($comp),
            'lancamentos' => $this->folha->lancamentosPorCompetencia($comp),
            'funcionarios' => $this->funcionarioRepo->findBy(['empresa' => $empresa, 'status' => 'ATIVO'], ['nome' => 'ASC']),
        ]);
    }

    #[Route('/{id}/exportar', name: 'app_rh_folha_export', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function export(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $comp = $this->folha->loadForEmpresa($empresa, $id);
        $csv = $this->folha->exportCsv($comp);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="folha_' . $comp->getReferencia() . '.csv"',
        ]);
    }
}
