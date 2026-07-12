<?php

namespace App\Service\Clinic;

use App\Service\Organismo\OrganismoCopyService;

/**
 * Indica se o deploy atual é UnioClínica / Unio Saúde (não Uniowork Studio).
 */
final class ClinicPlatformScope
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
    ) {}

    public function isActive(): bool
    {
        return $this->organismoCopy->isClinicProfile();
    }
}
