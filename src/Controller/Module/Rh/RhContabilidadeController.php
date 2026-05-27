<?php

namespace App\Controller\Module\Rh;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Service\Rh\RhProvisaoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/contabilidade')]
#[IsGranted('ROLE_USER')]
class RhContabilidadeController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/contabilidade/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhProvisaoService $provisao,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_contabilidade', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ref = trim((string) $request->query->get('ref', (new \DateTimeImmutable())->format('Y-m')));

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_contabilidade_calc');
                /** @var User $user */
                $user = $this->getUser();
                $referencia = (string) $request->request->get('referencia', $ref);
                $this->provisao->calculate($empresa, $referencia, $user);
                $this->addFlash('success', 'Provisões calculadas para ' . $referencia . '.');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_contabilidade', ['ref' => $ref]);
        }

        return $this->render(self::T . 'index.html.twig', [
            'provisoes' => $this->provisao->listForEmpresa($empresa, $ref !== '' ? $ref : null),
            'referencia' => $ref,
        ]);
    }
}
