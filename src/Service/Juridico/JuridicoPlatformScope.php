<?php

namespace App\Service\Juridico;

use App\Service\Organismo\OrganismoCopyService;

/** Indica se o deploy atual é Unio Jurídico. */
final class JuridicoPlatformScope
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
    ) {}

    public function isActive(): bool
    {
        return $this->organismoCopy->isJuridicoProfile();
    }
}
