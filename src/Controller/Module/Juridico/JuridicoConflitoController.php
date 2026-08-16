<?php

namespace App\Controller\Module\Juridico;

use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoConflitoCheckRepository;
use App\Service\Juridico\JuridicoConflitoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/conflitos')]
#[IsGranted('ROLE_USER')]
class JuridicoConflitoController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoConflitoService $conflitos,
        private JuridicoConflitoCheckRepository $repo,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_conflitos')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $resultado = null;
        if ($request->isMethod('POST')) {
            $this->requireCsrf($request, 'juridico_conflito_form');
            try {
                $resultado = $this->conflitos->verificar($empresa, (string) $request->request->get('nome', ''));
            } catch (JuridicoProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $historico = $this->repo->findForEmpresa($empresa);
        $alertas = \count(array_filter($historico, static fn ($c) => $c->getResultado() !== 'livre'));

        return $this->render('modules/juridico/conflitos_list.html.twig', [
            'historico' => $historico,
            'resultado' => $resultado,
            'metricas' => [
                'verificacoes' => \count($historico),
                'alertas' => $alertas,
            ],
        ]);
    }
}
