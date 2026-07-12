<?php

namespace App\Controller\Beneficiary;

use App\Service\Beneficiary\BeneficiaryAccessService;
use App\Service\Wallet\ClinicWalletPassService;
use App\Wallet\WalletPassType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Wallet pass para beneficiário autenticado na sessão.
 */
final class BeneficiaryWalletController extends AbstractController
{
    public function __construct(
        private BeneficiaryAccessService $access,
        private ClinicWalletPassService $wallet,
    ) {}

    #[Route('/carteirinha-digital/carteira/apple.pkpass', name: 'app_carteirinha_wallet_apple', methods: ['GET'])]
    public function carteirinhaApple(): Response
    {
        return $this->appleForSession(WalletPassType::Carteirinha, true);
    }

    #[Route('/carteirinha-digital/carteira/google', name: 'app_carteirinha_wallet_google', methods: ['GET'])]
    public function carteirinhaGoogle(): Response
    {
        return $this->googleForSession(WalletPassType::Carteirinha, true);
    }

    #[Route('/comprovante-procedimento/carteira/apple.pkpass', name: 'app_comprovante_wallet_apple', methods: ['GET'])]
    public function comprovanteApple(): Response
    {
        return $this->appleForSession(WalletPassType::Comprovante, false);
    }

    #[Route('/comprovante-procedimento/carteira/google', name: 'app_comprovante_wallet_google', methods: ['GET'])]
    public function comprovanteGoogle(): Response
    {
        return $this->googleForSession(WalletPassType::Comprovante, false);
    }

    private function appleForSession(WalletPassType $type, bool $requireCarteirinhaUnlock): Response
    {
        $paciente = $this->requirePatient($type, $requireCarteirinhaUnlock);
        if ($paciente === null) {
            return $this->redirectToAccess($type);
        }

        if (!$this->wallet->isAppleReady()) {
            $this->addFlash('error', 'Apple Wallet ainda não está disponível nesta clínica.');

            return $this->redirectToDocument($type);
        }

        $payload = $this->wallet->buildPayload($paciente, $type);
        $binary = $this->wallet->buildApplePkpass($payload);

        $response = new Response($binary);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $type->appleFilename(),
        );
        $response->headers->set('Content-Type', 'application/vnd.apple.pkpass');
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Length', (string) strlen($binary));

        return $response;
    }

    private function googleForSession(WalletPassType $type, bool $requireCarteirinhaUnlock): Response
    {
        $paciente = $this->requirePatient($type, $requireCarteirinhaUnlock);
        if ($paciente === null) {
            return $this->redirectToAccess($type);
        }

        if (!$this->wallet->isGoogleReady()) {
            $this->addFlash('error', 'Google Wallet ainda não está disponível nesta clínica.');

            return $this->redirectToDocument($type);
        }

        $payload = $this->wallet->buildPayload($paciente, $type);
        $url = $this->wallet->buildGoogleSaveUrl($payload);

        return $this->redirect($url);
    }

    private function requirePatient(WalletPassType $type, bool $requireCarteirinhaUnlock): ?\App\Entity\PosOperatorioPaciente
    {
        if ($this->access->isDemoSession()) {
            return null;
        }

        if ($requireCarteirinhaUnlock && !$this->access->isCarteirinhaUnlocked()) {
            return null;
        }

        if (!$this->access->isGranted()) {
            return null;
        }

        $paciente = $this->access->findGrantedPatient();
        if ($paciente === null || !$this->wallet->patientHasActiveDocument($paciente, $type)) {
            return null;
        }

        return $paciente;
    }

    private function redirectToAccess(WalletPassType $type): Response
    {
        return match ($type) {
            WalletPassType::Carteirinha => $this->redirectToRoute('app_carteirinha_digital'),
            WalletPassType::Comprovante => $this->redirectToRoute('app_comprovante_procedimento'),
        };
    }

    private function redirectToDocument(WalletPassType $type): Response
    {
        return match ($type) {
            WalletPassType::Carteirinha => $this->redirectToRoute('app_carteirinha_digital', ['passo' => '4']),
            WalletPassType::Comprovante => $this->redirectToRoute('app_comprovante_procedimento', ['passo' => '3']),
        };
    }
}
