<?php

namespace App\Controller\Api;

use App\Service\PosOperatorio\Payment\ClinicAsaasPaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/asaas/webhook')]
final class AsaasWebhookController extends AbstractController
{
    public function __construct(
        private ClinicAsaasPaymentService $payments,
        private string $webhookToken = '',
    ) {}

    #[Route('', name: 'app_api_asaas_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if ($this->webhookToken === '' || !$this->tokenValid($request)) {
            return $this->json(['error' => 'Token inválido'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent() ?: '{}', true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        $event = (string) ($payload['event'] ?? '');
        $payment = $payload['payment'] ?? null;
        if (!\is_array($payment)) {
            return $this->json(['ok' => true, 'ignored' => true]);
        }

        $paymentId = (string) ($payment['id'] ?? '');
        if ($paymentId === '') {
            return $this->json(['ok' => true, 'ignored' => true]);
        }

        if (!\in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            return $this->json(['ok' => true, 'event' => $event, 'ignored' => true]);
        }

        $value = isset($payment['value']) ? (float) $payment['value'] : null;
        $ok = $this->payments->markPaidFromWebhook($paymentId, $value);

        return $this->json(['ok' => true, 'matched' => $ok, 'event' => $event]);
    }

    private function tokenValid(Request $request): bool
    {
        $token = (string) (
            $request->headers->get('asaas-access-token')
            ?? $request->headers->get('asaastoken')
            ?? ''
        );

        return $token !== '' && hash_equals($this->webhookToken, $token);
    }
}
