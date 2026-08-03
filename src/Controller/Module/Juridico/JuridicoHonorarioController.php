<?php

namespace App\Controller\Module\Juridico;

use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\UserRepository;
use App\Service\Juridico\JuridicoHonorarioService;
use App\Service\Juridico\JuridicoModuleMetricsService;
use App\Service\Juridico\JuridicoProcessoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/honorarios')]
#[IsGranted('ROLE_USER')]
class JuridicoHonorarioController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoHonorarioService $honorarios,
        private JuridicoProcessoService $processos,
        private JuridicoProcessoRepository $processoRepo,
        private UserRepository $userRepo,
        private JuridicoModuleMetricsService $moduleMetrics,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_honorarios')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $mes = (string) $request->query->get('mes', '') ?: (new \DateTimeImmutable('today'))->format('Y-m');
        $advogadoId = (int) $request->query->get('advogado_id', 0);

        $resumo = $this->honorarios->resumoPorAdvogado($empresa, $mes);
        $ativosPorResponsavel = $this->processoRepo->countAtivosGroupedByResponsavel($empresa);
        $resultadosPorResponsavel = $this->processoRepo->resultadosGroupedByResponsavel($empresa);

        $resumoRows = array_map(function (array $row) use ($ativosPorResponsavel, $resultadosPorResponsavel) {
            $id = (string) $row['advogado']->getId();
            $res = $resultadosPorResponsavel[$id] ?? ['total' => 0, 'favoraveis' => 0];

            return [
                'advogado' => $row['advogado'],
                'horas' => $row['horas'],
                'receita' => $row['receita'],
                'processos_ativos' => $ativosPorResponsavel[$id] ?? 0,
                'taxa_exito' => $res['total'] > 0 ? round(($res['favoraveis'] / $res['total']) * 100, 1) : null,
            ];
        }, $resumo);

        return $this->render('modules/juridico/honorarios_list.html.twig', [
            'resumo' => $resumoRows,
            'lancamentos' => $this->honorarios->findForEmpresa($empresa, $advogadoId ?: null, $mes),
            'receita_mes' => $this->honorarios->receitaMes($empresa, $mes),
            'horas_mes' => $this->honorarios->horasMes($empresa, $mes),
            'metricas' => $this->moduleMetrics->honorarios($empresa),
            'advogados' => $this->userRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
            'processos' => $this->processos->listForSelect($empresa),
            'filter_mes' => $mes,
            'filter_advogado_id' => $advogadoId,
            'open_novo' => $request->query->getBoolean('open_novo'),
        ]);
    }

    #[Route('/novo', name: 'app_juridico_honorario_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_juridico_honorarios', ['open_novo' => 1]);
        }

        try {
            $this->requireCsrf($request, 'juridico_honorario_form');
            $this->honorarios->create($empresa, $request->request->all());
            $this->addFlash('success', 'Lançamento registrado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_honorarios');
    }

    #[Route('/{id}/editar', name: 'app_juridico_honorario_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $lancamento = $this->honorarios->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'juridico_honorario_form');
                $this->honorarios->update($lancamento, $request->request->all());
                $this->addFlash('success', 'Lançamento atualizado.');

                return $this->redirectToRoute('app_juridico_honorarios');
            } catch (JuridicoProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('modules/juridico/honorario_editar.html.twig', [
            'lancamento' => $lancamento,
            'advogados' => $this->userRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
            'processos' => $this->processos->listForSelect($empresa),
        ]);
    }

    #[Route('/{id}/excluir', name: 'app_juridico_honorario_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $lancamento = $this->honorarios->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_honorario_excluir_' . $id);
        $this->honorarios->delete($lancamento);
        $this->addFlash('success', 'Lançamento excluído.');

        return $this->redirectToRoute('app_juridico_honorarios');
    }
}
