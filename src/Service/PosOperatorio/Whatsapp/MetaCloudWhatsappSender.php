<?php

namespace App\Service\PosOperatorio\Whatsapp;

use App\Entity\Empresa;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envio de texto via Meta WhatsApp Cloud API.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-messages
 */
final class MetaCloudWhatsappSender implements ClinicWhatsappSenderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $token,
        private string $phoneNumberId,
        private string $graphVersion,
        private LoggerInterface $logger,
    ) {}

    public function isLive(): bool
    {
        return $this->token !== '' && $this->phoneNumberId !== '';
    }

    public function providerName(): string
    {
        return 'meta';
    }

    public function send(Empresa $empresa, string $toE164, string $text, array $context = []): ClinicWhatsappResult
    {
        if (!$this->isLive()) {
            return ClinicWhatsappResult::skipped('meta', 'Token ou phone_number_id ausente', $toE164);
        }

        $digits = preg_replace('/\D+/', '', $toE164) ?? '';
        if (strlen($digits) < 10) {
            return ClinicWhatsappResult::failed('meta', 'Telefone inválido', $toE164);
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            rawurlencode($this->graphVersion),
            rawurlencode($this->phoneNumberId),
        );

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $digits,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => true,
                        'body' => mb_substr($text, 0, 4096),
                    ],
                ],
                'timeout' => 12,
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($status >= 200 && $status < 300) {
                $messageId = (string) ($data['messages'][0]['id'] ?? '');

                return ClinicWhatsappResult::sent('meta', $messageId !== '' ? $messageId : 'ok', $digits);
            }

            $error = (string) ($data['error']['message'] ?? ('HTTP '.$status));
            $this->logger->warning('WhatsApp Meta send failed', [
                'empresa_id' => $empresa->getId(),
                'status' => $status,
                'error' => $error,
                'event' => $context['event'] ?? null,
            ]);

            return ClinicWhatsappResult::failed('meta', $error, $digits);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('WhatsApp Meta transport error', [
                'empresa_id' => $empresa->getId(),
                'error' => $e->getMessage(),
            ]);

            return ClinicWhatsappResult::failed('meta', $e->getMessage(), $digits);
        } catch (\Throwable $e) {
            $this->logger->warning('WhatsApp Meta unexpected error', [
                'empresa_id' => $empresa->getId(),
                'error' => $e->getMessage(),
            ]);

            return ClinicWhatsappResult::failed('meta', $e->getMessage(), $digits);
        }
    }
}
