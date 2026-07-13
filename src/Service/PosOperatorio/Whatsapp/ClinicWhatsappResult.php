<?php

namespace App\Service\PosOperatorio\Whatsapp;

final class ClinicWhatsappResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly string $status,
        public readonly string $provider,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
        public readonly ?string $toE164 = null,
    ) {}

    public static function skipped(string $provider, string $reason, ?string $toE164 = null): self
    {
        return new self(false, 'skipped', $provider, null, $reason, $toE164);
    }

    public static function failed(string $provider, string $error, ?string $toE164 = null): self
    {
        return new self(false, 'failed', $provider, null, $error, $toE164);
    }

    public static function sent(string $provider, string $messageId, string $toE164): self
    {
        return new self(true, 'sent', $provider, $messageId, null, $toE164);
    }
}
