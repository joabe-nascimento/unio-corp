<?php

namespace App\Service\PosOperatorio\Whatsapp;

use App\Entity\Empresa;

/**
 * Sem credenciais Meta: não dispara HTTP; a UI continua com wa.me.
 */
final class NoopWhatsappSender implements ClinicWhatsappSenderInterface
{
    public function isLive(): bool
    {
        return false;
    }

    public function providerName(): string
    {
        return 'noop';
    }

    public function send(Empresa $empresa, string $toE164, string $text, array $context = []): ClinicWhatsappResult
    {
        return ClinicWhatsappResult::skipped('noop', 'WhatsApp API não configurada (WHATSAPP_META_*)', $toE164);
    }
}
