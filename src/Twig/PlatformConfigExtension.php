<?php

namespace App\Twig;

use App\Service\PlatformConfigService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Injeta `platform_config` como variável global em todos os templates Twig.
 */
class PlatformConfigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private PlatformConfigService $config) {}

    /** @return array<string,mixed> */
    public function getGlobals(): array
    {
        return [
            'platform_config' => $this->config->all(),
        ];
    }
}
