<?php

namespace App\Service\PosOperatorio\Whatsapp;

use App\Entity\Empresa;

interface ClinicWhatsappSenderInterface
{
    public function isLive(): bool;

    public function providerName(): string;

    /**
     * @param array{event?: string, paciente_id?: int|null, agendamento_id?: int|null} $context
     */
    public function send(Empresa $empresa, string $toE164, string $text, array $context = []): ClinicWhatsappResult;
}
