<?php

namespace App\Controller\Module\Rh;

use App\Entity\RhFerias;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Service\RhFeriasService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/ferias')]
#[IsGranted('ROLE_USER')]
class RhFeriasController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhFeriasService $ferias,
        private FuncionarioRepository $funcionarioRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_ferias')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = (string) $request->query->get('status', '');
        $q = trim((string) $request->query->get('q', ''));

        return $this->render(self::T . 'ferias.html.twig', [
            'solicitacoes' => $this->ferias->listForEmpresa($empresa, $status !== '' ? $status : null, $q !== '' ? $q : null),
            'filter_status' => $status,
            'filter_q' => $q,
        ]);
    }

    #[Route('/nova', name: 'app_rh_ferias_nova', methods: ['GET', 'POST'])]
    public function nova(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $funcionarios = $this->funcionarioRepo->findBy(['empresa' => $empresa, 'status' => 'ATIVO'], ['nome' => 'ASC']);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_ferias_form');
                $fid = (int) $request->request->get('funcionario_id', 0);
                $func = $fid > 0 ? $this->funcionarioRepo->findOneBy(['id' => $fid, 'empresa' => $empresa]) : null;
                if (!$func) {
                    throw new RhProcessException('Selecione um funcionário.');
                }
                $inicio = $this->parseDate($request->request->get('data_inicio'));
                $fim = $this->parseDate($request->request->get('data_fim'));
                if (!$inicio || !$fim) {
                    throw new RhProcessException('Informe as datas de início e fim.');
                }
                /** @var User $user */
                $user = $this->getUser();
                $f = $this->ferias->solicitar($empresa, $func, $inicio, $fim, $request->request->get('observacoes'), $user);
                $this->addFlash('success', 'Solicitação de férias registrada.');

                return $this->redirectToRoute('app_rh_ferias_show', ['id' => $f->getId()]);
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(self::T . 'ferias_form.html.twig', ['empresa' => $empresa, 'funcionarios' => $funcionarios]);
    }

    #[Route('/{id}', name: 'app_rh_ferias_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ferias = $this->ferias->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_ferias_action');
                /** @var User $user */
                $user = $this->getUser();
                $action = $request->request->get('action');
                match ($action) {
                    'aprovar' => $this->ferias->aprovar($ferias, $user),
                    'rejeitar' => $this->ferias->rejeitar($ferias, $user, (string) $request->request->get('motivo', '')),
                    'iniciar' => $this->ferias->iniciarGozo($ferias),
                    'concluir' => $this->ferias->concluir($ferias),
                    default => throw new RhProcessException('Ação inválida.'),
                };
                $this->addFlash('success', 'Solicitação atualizada.');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_ferias_show', ['id' => $id]);
        }

        return $this->render(self::T . 'ferias_show.html.twig', ['ferias' => $ferias]);
    }
}
