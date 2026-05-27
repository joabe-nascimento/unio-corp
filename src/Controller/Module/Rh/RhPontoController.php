<?php

namespace App\Controller\Module\Rh;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Service\Rh\RhPontoService;
use App\Service\Rh\RhPortalService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/ponto')]
#[IsGranted('ROLE_USER')]
class RhPontoController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/ponto/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhPontoService $ponto,
        private RhPortalService $portal,
        private FuncionarioRepository $funcionarioRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_ponto', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();
        $funcionario = $this->portal->resolveFuncionarioForUser($empresa, $user);
        $data = $this->parseDate($request->query->get('data')) ?? new \DateTimeImmutable('today');

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_ponto_batida');
                $fid = (int) $request->request->get('funcionario_id', 0);
                $func = $fid > 0
                    ? $this->funcionarioRepo->findOneBy(['id' => $fid, 'empresa' => $empresa])
                    : $funcionario;
                if (!$func) {
                    throw new RhProcessException('Selecione um colaborador para registrar a batida.');
                }
                $this->ponto->registrarBatida(
                    $empresa,
                    $func,
                    (string) $request->request->get('tipo', 'ENTRADA'),
                    'WEB',
                    (string) $request->request->get('observacao', ''),
                    $user,
                );
                $this->addFlash('success', 'Batida registrada.');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_ponto', ['data' => $data->format('Y-m-d')]);
        }

        $registros = $funcionario ? $this->ponto->listByFuncionarioAndDate($funcionario, $data) : [];

        return $this->render(self::T . 'index.html.twig', [
            'funcionario' => $funcionario,
            'funcionarios' => $this->funcionarioRepo->findBy(['empresa' => $empresa, 'status' => 'ATIVO'], ['nome' => 'ASC']),
            'registros' => $registros,
            'data_filtro' => $data,
        ]);
    }
}
