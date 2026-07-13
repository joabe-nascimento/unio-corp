<?php

namespace App\Service\PosOperatorio\Whatsapp;

use App\Entity\Empresa;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Meta WhatsApp Cloud API: template HSM (fora da janela 24h) com fallback para texto.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
 */
final class MetaCloudWhatsappSender implements ClinicWhatsappSenderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $token,
        private string $phoneNumberId,
        private string $graphVersion,
        private LoggerInterface $logger,
        private string $templateAgenda = '',
        private string $templateQuestionario = '',
        private string $templateLang = 'pt_BR',
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

        $event = (string) ($context['event'] ?? '');
        $templateName = $this->resolveTemplateName($event, $context);
        if ($templateName !== null) {
            $params = $this->normalizeTemplateParams($context['template_params'] ?? []);
            $templateResult = $this->postMessage($empresa, $digits, $this->templatePayload($digits, $templateName, $params), $event);
            if ($templateResult->sent) {
                return $templateResult;
            }

            $this->logger->info('WhatsApp Meta template failed; falling back to text', [
                'empresa_id' => $empresa->getId(),
                'template' => $templateName,
                'error' => $templateResult->error,
                'event' => $event,
            ]);
        }

        return $this->postMessage($empresa, $digits, $this->textPayload($digits, $text), $event);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveTemplateName(string $event, array $context): ?string
    {
        if (isset($context['template']) && \is_string($context['template']) && trim($context['template']) !== '') {
            return trim($context['template']);
        }

        return match ($event) {
            'agenda_confirmacao' => $this->templateAgenda !== '' ? $this->templateAgenda : null,
            'questionario_pendente' => $this->templateQuestionario !== '' ? $this->templateQuestionario : null,
            default => null,
        };
    }

    /**
     * @param mixed $params
     *
     * @return list<string>
     */
    private function normalizeTemplateParams(mixed $params): array
    {
        if (!\is_array($params)) {
            return [];
        }

        $out = [];
        foreach ($params as $value) {
            if (\is_string($value) || is_numeric($value)) {
                $out[] = mb_substr(trim((string) $value), 0, 1024);
            }
        }

        return $out;
    }

    /**
     * @param list<string> $params
     *
     * @return array<string, mixed>
     */
    private function templatePayload(string $digits, string $templateName, array $params): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $digits,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $this->templateLang !== '' ? $this->templateLang : 'pt_BR'],
            ],
        ];

        if ($params !== []) {
            $bodyParams = [];
            foreach ($params as $text) {
                $bodyParams[] = ['type' => 'text', 'text' => $text !== '' ? $text : '-'];
            }
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => $bodyParams,
            ]];
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function textPayload(string $digits, string $text): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $digits,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => mb_substr($text, 0, 4096),
            ],
        ];
    }

    /** @param array<string, mixed> $json */
    private function postMessage(Empresa $empresa, string $digits, array $json, string $event): ClinicWhatsappResult
    {
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
                'json' => $json,
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
                'event' => $event !== '' ? $event : null,
                'type' => $json['type'] ?? null,
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
