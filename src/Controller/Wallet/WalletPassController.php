<?php

namespace App\Controller\Wallet;

use App\Service\Organismo\OrganismoCopyService;
use App\Service\Wallet\ClinicWalletPassService;
use App\Wallet\WalletPassType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Download de passes via token (compartilhamento pela equipe).
 */
#[Route('/wallet')]
final class WalletPassController extends AbstractController
{
    public function __construct(
        private ClinicWalletPassService $wallet,
    ) {}

    #[Route('/apple/{tipo}/{token}.pkpass', name: 'app_wallet_apple', requirements: ['tipo' => 'carteirinha|comprovante', 'token' => '.+'])]
    public function apple(string $tipo, string $token): Response
    {
        $resolved = $this->resolveToken($tipo, $token);
        if ($resolved === null) {
            throw $this->createNotFoundException('Link de carteira inválido ou expirado.');
        }

        if (!$this->wallet->isAppleReady()) {
            return $this->walletUnavailable('Apple Wallet');
        }

        $payload = $this->wallet->buildPayload($resolved['paciente'], $resolved['type']);
        $binary = $this->wallet->buildApplePkpass($payload);

        return $this->pkpassResponse($binary, $resolved['type']);
    }

    #[Route('/google/{tipo}/{token}', name: 'app_wallet_google', requirements: ['tipo' => 'carteirinha|comprovante', 'token' => '.+'])]
    public function google(string $tipo, string $token): Response
    {
        $resolved = $this->resolveToken($tipo, $token);
        if ($resolved === null) {
            throw $this->createNotFoundException('Link de carteira inválido ou expirado.');
        }

        if (!$this->wallet->isGoogleReady()) {
            return $this->walletUnavailable('Google Wallet');
        }

        $payload = $this->wallet->buildPayload($resolved['paciente'], $resolved['type']);
        $url = $this->wallet->buildGoogleSaveUrl($payload);

        return $this->redirect($url);
    }

    /** @return array{paciente: \App\Entity\PosOperatorioPaciente, type: WalletPassType}|null */
    private function resolveToken(string $tipo, string $token): ?array
    {
        $type = WalletPassType::tryFromRoute($tipo);
        if ($type === null) {
            return null;
        }

        $resolved = $this->wallet->findPatientForToken($token);
        if ($resolved === null || $resolved['type'] !== $type) {
            return null;
        }

        return $resolved;
    }

    private function pkpassResponse(string $binary, WalletPassType $type): Response
    {
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

    private function walletUnavailable(string $provider): Response
    {
        return new Response(
            '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Carteira digital</title></head>'
            . '<body style="font-family:system-ui,sans-serif;padding:2rem;max-width:36rem;margin:auto">'
            . '<h1>Carteira digital indisponível</h1>'
            . '<p>' . htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') . ' ainda não está configurado neste servidor.</p>'
            . '<p>Peça à clínica para ativar as credenciais <code>WALLET_*</code> no ambiente.</p>'
            . '</body></html>',
            Response::HTTP_SERVICE_UNAVAILABLE,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
