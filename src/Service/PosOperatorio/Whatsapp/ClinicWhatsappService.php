<?php

namespace App\Service\PosOperatorio\Whatsapp;

use App\Entity\ClinicOutboundMessage;
use App\Entity\Empresa;
use App\Repository\ClinicOutboundMessageRepository;

/**
 * Normaliza telefone, dispara o sender e grava log de entrega.
 */
final class ClinicWhatsappService
{
    public function __construct(
        private ClinicWhatsappSenderInterface $sender,
        private ClinicOutboundMessageRepository $outboundMessages,
    ) {}

    public function isLive(): bool
    {
        return $this->sender->isLive();
    }

    public function providerName(): string
    {
        return $this->sender->providerName();
    }

    /**
     * @param array{event?: string, paciente_id?: int|null, agendamento_id?: int|null} $context
     */
    public function send(Empresa $empresa, ?string $telefone, string $text, array $context = []): ClinicWhatsappResult
    {
        $event = (string) ($context['event'] ?? 'whatsapp');
        $e164 = $this->normalizeE164($telefone);

        if ($e164 === null) {
            $result = ClinicWhatsappResult::failed($this->sender->providerName(), 'Telefone ausente ou inválido');
            $this->persist($empresa, $event, '', $result, $text);

            return $result;
        }

        $result = $this->sender->send($empresa, $e164, $text, $context);
        $this->persist($empresa, $event, $result->toE164 ?? $e164, $result, $text);

        return $result;
    }

    public function normalizeE164(?string $telefone): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $telefone) ?? '';
        if (strlen($phone) < 10) {
            return null;
        }

        if (!str_starts_with($phone, '55')) {
            $phone = '55'.ltrim($phone, '0');
        }

        return $phone;
    }

    private function persist(
        Empresa $empresa,
        string $event,
        string $destino,
        ClinicWhatsappResult $result,
        string $text,
    ): void {
        $message = (new ClinicOutboundMessage())
            ->setEmpresa($empresa)
            ->setCanal(ClinicOutboundMessage::CANAL_WHATSAPP)
            ->setEvento($event)
            ->setDestino($destino)
            ->setStatus($result->status)
            ->setProvider($result->provider)
            ->setProviderMessageId($result->providerMessageId)
            ->setErro($result->error)
            ->setCorpoPreview(mb_substr(trim($text), 0, 240));

        $this->outboundMessages->save($message);
    }
}
