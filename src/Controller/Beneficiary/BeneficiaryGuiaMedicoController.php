<?php

namespace App\Controller\Beneficiary;

use App\Service\Beneficiary\BeneficiaryAccessService;
use App\Service\Beneficiary\BeneficiaryContentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/guia-medico')]
final class BeneficiaryGuiaMedicoController extends AbstractController
{
    public function __construct(
        private BeneficiaryAccessService $access,
        private BeneficiaryContentService $content,
    ) {}

    #[Route('', name: 'app_guia_medico_beneficiario', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handlePost($request);
        }

        if ($this->access->isGranted()) {
            return $this->renderGuia();
        }

        $step = (string) $request->query->get('passo', '1');
        if ($step === '2') {
            return $this->stepConfirm();
        }

        return $this->stepIdentify();
    }

    #[Route('/sair', name: 'app_guia_medico_beneficiario_sair', methods: ['GET'])]
    public function logout(): Response
    {
        $this->access->revoke();

        return $this->redirectToRoute('app_guia_medico_beneficiario');
    }

    private function handlePost(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('beneficiary_guia', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Sessão expirada. Tente novamente.');

            return $this->redirectToRoute('app_guia_medico_beneficiario');
        }

        $passo = (string) $request->request->get('passo', '1');

        if ($passo === '1') {
            $result = $this->access->storePendingIdentification(
                (string) $request->request->get('metodo', BeneficiaryAccessService::METHOD_CPF),
                (string) $request->request->get('identificador', ''),
            );
            if (!$result['ok']) {
                $this->addFlash('error', $result['error']);

                return $this->redirectToRoute('app_guia_medico_beneficiario');
            }

            return $this->redirectToRoute('app_guia_medico_beneficiario', ['passo' => '2']);
        }

        $result = $this->access->confirmIdentification((string) $request->request->get('confirmacao', ''));
        if (!$result['ok']) {
            $this->addFlash('error', $result['error']);

            return $this->redirectToRoute('app_guia_medico_beneficiario', ['passo' => '2']);
        }

        return $this->redirectToRoute('app_guia_medico_beneficiario');
    }

    private function stepIdentify(): Response
    {
        if ($this->access->isGranted()) {
            return $this->renderGuia();
        }

        return $this->render('beneficiary/guia_identificar.html.twig', [
            'demo_hints' => $this->access->demoAccessHints(),
            'demo_preview' => $this->access->demoPreview(),
        ]);
    }

    private function stepConfirm(): Response
    {
        if ($this->access->pendingIdentification() === null) {
            return $this->redirectToRoute('app_guia_medico_beneficiario');
        }

        return $this->render('beneficiary/guia_confirmar.html.twig', [
            'pending' => $this->access->pendingIdentification(),
            'demo_hints' => $this->access->demoAccessHints(),
        ]);
    }

    private function renderGuia(): Response
    {
        return $this->render('beneficiary/guia_portal.html.twig', array_merge(
            $this->content->buildGuiaView(),
            ['is_demo' => $this->access->isDemoSession()],
        ));
    }
}
