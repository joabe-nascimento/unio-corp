<?php

namespace App\Controller\Beneficiary;

use App\Service\Beneficiary\BeneficiaryAccessService;
use App\Service\Beneficiary\BeneficiaryContentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/comprovante-procedimento')]
final class BeneficiaryComprovanteController extends AbstractController
{
    public function __construct(
        private BeneficiaryAccessService $access,
        private BeneficiaryContentService $content,
    ) {}

    #[Route('', name: 'app_comprovante_procedimento', methods: ['GET', 'POST'])]
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

    #[Route('/sair', name: 'app_comprovante_procedimento_sair', methods: ['GET'])]
    public function logout(): Response
    {
        $this->access->revoke();

        return $this->redirectToRoute('app_comprovante_procedimento');
    }

    private function handlePost(Request $request, string $step): Response
    {
        if (!$this->isCsrfTokenValid('beneficiary_comprovante', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Sessão expirada. Tente novamente.');

            return $this->redirectToRoute('app_comprovante_procedimento');
        }

        if ($step === '1') {
            $result = $this->access->storePendingIdentification(
                (string) $request->request->get('metodo', BeneficiaryAccessService::METHOD_CPF),
                (string) $request->request->get('identificador', ''),
            );
            if (!$result['ok']) {
                $this->addFlash('error', $result['error']);

                return $this->redirectToRoute('app_comprovante_procedimento');
            }

            return $this->redirectToRoute('app_comprovante_procedimento', ['passo' => '2']);
        }

        if ($step === '2') {
            $result = $this->access->confirmIdentification((string) $request->request->get('confirmacao', ''));
            if (!$result['ok']) {
                $this->addFlash('error', $result['error']);

                return $this->redirectToRoute('app_comprovante_procedimento', ['passo' => '2']);
            }

            return $this->redirectToRoute('app_comprovante_procedimento', ['passo' => '3']);
        }

        if ($step === '3') {
            if (!$this->access->isGranted()) {
                return $this->redirectToRoute('app_comprovante_procedimento');
            }
            $this->access->unlockComprovante();

            return $this->redirectToRoute('app_comprovante_procedimento', ['passo' => '4']);
        }

        return $this->redirectToRoute('app_comprovante_procedimento');
    }

    private function stepIdentify(): Response
    {
        if ($this->access->isGranted()) {
            return $this->redirectToRoute('app_comprovante_procedimento', ['passo' => '3']);
        }

        return $this->render('beneficiary/comprovante_identificar.html.twig', [
            'step' => 1,
            'total_steps' => 4,
            'demo_hints' => $this->access->demoAccessHints(),
            'demo_preview' => $this->access->demoComprovantePreview(),
        ]);
    }

    private function stepConfirm(): Response
    {
        $pending = $this->access->pendingIdentification();
        if ($pending === null) {
            return $this->redirectToRoute('app_comprovante_procedimento');
        }

        return $this->render('beneficiary/comprovante_confirmar.html.twig', [
            'step' => 2,
            'total_steps' => 4,
            'pending' => $pending,
            'demo_hints' => $this->access->demoAccessHints(),
        ]);
    }

    private function stepView(): Response
    {
        if (!$this->access->isGranted()) {
            return $this->redirectToRoute('app_comprovante_procedimento');
        }

        $proof = $this->content->buildComprovanteProof();
        if ($proof === null) {
            $this->addFlash('error', 'Nenhum comprovante ativo encontrado para este beneficiário.');

            return $this->redirectToRoute('app_comprovante_procedimento');
        }

        return $this->render('beneficiary/comprovante_ver.html.twig', [
            'step' => 3,
            'total_steps' => 4,
            'card' => $proof['card'],
            'theme' => $proof['theme'],
            'proof' => $proof['proof'],
            'is_demo' => $this->access->isDemoSession(),
            'verificacao_url' => $proof['verificacao_url'] ?? null,
        ]);
    }

    private function stepDownload(): Response
    {
        if (!$this->access->isComprovanteUnlocked()) {
            return $this->redirectToRoute('app_comprovante_procedimento', ['passo' => '3']);
        }

        $proof = $this->content->buildComprovanteProof();
        if ($proof === null) {
            return $this->redirectToRoute('app_comprovante_procedimento');
        }

        return $this->render('beneficiary/comprovante_baixar.html.twig', [
            'step' => 4,
            'total_steps' => 4,
            'card' => $proof['card'],
            'theme' => $proof['theme'],
            'proof' => $proof['proof'],
            'is_demo' => $this->access->isDemoSession(),
            'verificacao_url' => $proof['verificacao_url'] ?? null,
        ]);
    }
}
