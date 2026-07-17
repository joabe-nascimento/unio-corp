<?php

namespace App\Controller\Api;

use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappInboundConfirmService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/whatsapp/meta')]
final class WhatsappMetaWebhookController extends AbstractController
{
    public function __construct(
        private ClinicWhatsappInboundConfirmService $inbound,
        private string $verifyToken = '',
        private string $appSecret = '',
    ) {}

    #[Route('', name: 'app_api_whatsapp_meta_webhook', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->verify($request);
        }

        return $this->receive($request);
    }

    private function verify(Request $request): Response
    {
        $mode = (string) $request->query->get('hub_mode', $request->query->get('hub.mode', ''));
        $token = (string) $request->query->get('hub_verify_token', $request->query->get('hub.verify_token', ''));
        $challenge = (string) $request->query->get('hub_challenge', $request->query->get('hub.challenge', ''));

        if ($mode === 'subscribe' && $this->verifyToken !== '' && hash_equals($this->verifyToken, $token)) {
            return new Response($challenge, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
        }

        return new Response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    private function receive(Request $request): Response
    {
        $raw = $request->getContent() ?: '';
        if ($this->appSecret === '' || !$this->validSignature($request, $raw)) {
            return $this->json(['error' => 'Assinatura inválida'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($raw, true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->inbound->handleMetaPayload($payload);

        return $this->json(['ok' => true] + $result);
    }

    private function validSignature(Request $request, string $raw): bool
    {
        $header = (string) $request->headers->get('X-Hub-Signature-256', '');
        if (!str_starts_with($header, 'sha256=')) {
            return false;
        }
        $expected = 'sha256='.hash_hmac('sha256', $raw, $this->appSecret);

        return hash_equals($expected, $header);
    }
}
