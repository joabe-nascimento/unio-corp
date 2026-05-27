<?php

namespace App\Controller\Module\Rh;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Service\Rh\RhEsocialService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/esocial')]
#[IsGranted('ROLE_USER')]
class RhEsocialController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/esocial/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhEsocialService $esocial,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_esocial', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_esocial_lote');
                /** @var User $user */
                $user = $this->getUser();
                $this->esocial->createLote(
                    $empresa,
                    (string) $request->request->get('referencia', (new \DateTimeImmutable())->format('Y-m')),
                    (string) $request->request->get('tipo_evento', 'S1200'),
                    $user,
                );
                $this->addFlash('success', 'Lote eSocial criado (stub).');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_esocial');
        }

        return $this->render(self::T . 'index.html.twig', [
            'lotes' => $this->esocial->listForEmpresa($empresa),
        ]);
    }
}
