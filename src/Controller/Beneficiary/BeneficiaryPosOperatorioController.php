<?php

namespace App\Controller\Beneficiary;

use App\Service\Beneficiary\BeneficiaryAccessService;
use App\Service\Beneficiary\BeneficiaryContentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Portal pós-operatório do beneficiário — mesmo padrão de acesso do guia/carteirinha.
 */
#[Route('/portal-pos-operatorio')]
final class BeneficiaryPosOperatorioController extends AbstractController
{
    public function __construct(
        private BeneficiaryAccessService $access,
        private BeneficiaryContentService $content,
    ) {}

    #[Route('', name: 'app_portal_pos_operatorio', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handlePost($request);
        }

        if ($this->access->isGranted()) {
            return $this->renderPortal();
        }

        $step = (string) $request->query->get('passo', '1');
        if ($step === '2') {
            return $this->stepConfirm();
        }

        return $this->stepIdentify();
    }

    #[Route('/sair', name: 'app_portal_pos_operatorio_sair', methods: ['GET'])]
    public function logout(): Response
    {
        $this->access->revoke();

        return $this->redirectToRoute('app_portal_pos_operatorio');
    }

    private function handlePost(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('beneficiary_posop', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Sessão expirada. Tente novamente.');

            return $this->redirectToRoute('app_portal_pos_operatorio');
        }

        $passo = (string) $request->request->get('passo', '1');

        if ($passo === '1') {
            $result = $this->access->storePendingIdentification(
                (string) $request->request->get('metodo', BeneficiaryAccessService::METHOD_CPF),
                (string) $request->request->get('identificador', ''),
            );
            if (!$result['ok']) {
                $this->addFlash('error', $result['error']);

                return $this->redirectToRoute('app_portal_pos_operatorio');
            }

            return $this->redirectToRoute('app_portal_pos_operatorio', ['passo' => '2']);
        }

        $result = $this->access->confirmIdentification((string) $request->request->get('confirmacao', ''));
        if (!$result['ok']) {
            $this->addFlash('error', $result['error']);

            return $this->redirectToRoute('app_portal_pos_operatorio', ['passo' => '2']);
        }

        return $this->redirectToRoute('app_portal_pos_operatorio');
    }

    private function stepIdentify(): Response
    {
        if ($this->access->isGranted()) {
            return $this->renderPortal();
        }

        return $this->render('beneficiary/posop_identificar.html.twig', [
            'demo_hints' => $this->access->demoAccessHints(),
            'demo_preview' => $this->access->demoPreview(),
        ]);
    }

    private function stepConfirm(): Response
    {
        if ($this->access->pendingIdentification() === null) {
            return $this->redirectToRoute('app_portal_pos_operatorio');
        }

        return $this->render('beneficiary/posop_confirmar.html.twig', [
            'pending' => $this->access->pendingIdentification(),
            'demo_hints' => $this->access->demoAccessHints(),
        ]);
    }

    private function renderPortal(): Response
    {
        return $this->render('beneficiary/posop_portal.html.twig', array_merge(
            $this->content->buildPosOperatorioView(),
            ['is_demo' => $this->access->isDemoSession()],
        ));
    }
}
