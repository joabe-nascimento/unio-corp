<?php

namespace App\Service\Organismo;

/**
 * Feature flags da transição Organismo (Colônia / Cena / Pulso / Lumen).
 */
final class OrganismoFeature
{
    public function __construct(
        private bool $enabled,
        private bool $pulsoAsHome,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isPulsoHome(): bool
    {
        return $this->enabled && $this->pulsoAsHome;
    }
}
