<?php

namespace App\Controller\Beneficiary;

use App\Service\Beneficiary\BeneficiaryAccessService;
use App\Service\Beneficiary\BeneficiaryContentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/carterinha-digital')]
final class BeneficiaryCarteirinhaController extends AbstractController
{
    public function __construct(
        private BeneficiaryAccessService $access,
        private BeneficiaryContentService $content,
    ) {}

    #[Route('', name: 'app_carteirinha_digital', methods: ['GET', 'POST'])]
    public function flow(Request $request): Response
    {
        $step = (string) $request->query->get('passo', '1');

        if ($request->isMethod('POST')) {
            return $this->handlePost($request, $step);
        }

        return match ($step) {
            '2' => $this->stepConfirm(),
            '3' => $this->stepView(),
            '4' => $this->stepDownload(),
            default => $this->stepIdentify(),
        };
    }

    #[Route('/sair', name: 'app_carteirinha_digital_sair', methods: ['GET'])]
    public function logout(): Response
    {
        $this->access->revoke();

        return $this->redirectToRoute('app_carteirinha_digital');
    }

    private function handlePost(Request $request, string $step): Response
    {
        if (!$this->isCsrfTokenValid('beneficiary_carteirinha', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Sessão expirada. Tente novamente.');

            return $this->redirectToRoute('app_carteirinha_digital');
        }

        if ($step === '1') {
            $result = $this->access->storePendingIdentification(
                (string) $request->request->get('metodo', BeneficiaryAccessService::METHOD_CPF),
                (string) $request->request->get('identificador', ''),
            );
            if (!$result['ok']) {
                $this->addFlash('error', $result['error']);

                return $this->redirectToRoute('app_carteirinha_digital');
            }

            return $this->redirectToRoute('app_carteirinha_digital', ['passo' => '2']);
        }

        if ($step === '2') {
            $result = $this->access->confirmIdentification((string) $request->request->get('confirmacao', ''));
            if (!$result['ok']) {
                $this->addFlash('error', $result['error']);

                return $this->redirectToRoute('app_carteirinha_digital', ['passo' => '2']);
            }

            return $this->redirectToRoute('app_carteirinha_digital', ['passo' => '3']);
        }

        if ($step === '3') {
            if (!$this->access->isGranted()) {
                return $this->redirectToRoute('app_carteirinha_digital');
            }
            $this->access->unlockCarteirinha();

            return $this->redirectToRoute('app_carteirinha_digital', ['passo' => '4']);
        }

        return $this->redirectToRoute('app_carteirinha_digital');
    }

    private function stepIdentify(): Response
    {
        if ($this->access->isGranted()) {
            return $this->redirectToRoute('app_carteirinha_digital', ['passo' => '3']);
        }

        return $this->render('beneficiary/carteirinha_identificar.html.twig', [
            'step' => 1,
            'total_steps' => 4,
            'demo_hints' => $this->access->demoAccessHints(),
            'demo_preview' => $this->access->demoPreview(),
        ]);
    }

    private function stepConfirm(): Response
    {
        if ($this->access->pendingIdentification() === null) {
            return $this->redirectToRoute('app_carteirinha_digital');
        }

        return $this->render('beneficiary/carteirinha_confirmar.html.twig', [
            'step' => 2,
            'total_steps' => 4,
            'pending' => $this->access->pendingIdentification(),
            'demo_hints' => $this->access->demoAccessHints(),
        ]);
    }

    private function stepView(): Response
    {
        if (!$this->access->isGranted()) {
            return $this->redirectToRoute('app_carteirinha_digital');
        }

        $card = $this->content->buildCarteirinhaCard();
        if ($card === null) {
            $this->addFlash('error', 'Nenhuma carteirinha ativa encontrada para este beneficiário.');
            $this->access->revoke();

            return $this->redirectToRoute('app_carteirinha_digital');
        }

        return $this->render('beneficiary/carteirinha_ver.html.twig', [
            'step' => 3,
            'total_steps' => 4,
            'card' => $card['card'],
            'theme' => $card['theme'],
            'is_demo' => $this->access->isDemoSession(),
        ]);
    }

    private function stepDownload(): Response
    {
        if (!$this->access->isCarteirinhaUnlocked()) {
            return $this->redirectToRoute('app_carteirinha_digital', ['passo' => '3']);
        }

        $card = $this->content->buildCarteirinhaCard();
        if ($card === null) {
            return $this->redirectToRoute('app_carteirinha_digital');
        }

        return $this->render('beneficiary/carteirinha_baixar.html.twig', [
            'step' => 4,
            'total_steps' => 4,
            'card' => $card['card'],
            'theme' => $card['theme'],
            'is_demo' => $this->access->isDemoSession(),
        ]);
    }
}
