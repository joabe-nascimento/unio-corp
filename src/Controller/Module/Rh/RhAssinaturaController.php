<?php

namespace App\Controller\Module\Rh;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Service\Rh\RhAssinaturaService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/assinatura')]
#[IsGranted('ROLE_USER')]
class RhAssinaturaController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/assinatura/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhAssinaturaService $assinatura,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_assinatura', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_assinatura_envelope');
                /** @var User $user */
                $user = $this->getUser();
                $this->assinatura->createEnvelope($empresa, (string) $request->request->get('titulo', ''), $user);
                $this->addFlash('success', 'Envelope de assinatura criado (stub).');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_assinatura');
        }

        return $this->render(self::T . 'index.html.twig', [
            'envelopes' => $this->assinatura->listForEmpresa($empresa),
        ]);
    }
}
