<?php

namespace App\Twig;

use App\Config\JuridicoModuleRegistry;
use App\Service\Organismo\OrganismoCopyService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class JuridicoTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
    ) {
    }

    /** @return array<string, mixed> */
    public function getGlobals(): array
    {
        if (!$this->organismoCopy->isJuridicoProfile()) {
            return [
                'juridico_modules' => [],
                'juridico_module_sections' => [],
            ];
        }

        return [
            'juridico_modules' => JuridicoModuleRegistry::MODULES,
            'juridico_module_sections' => JuridicoModuleRegistry::grouped(),
        ];
    }
}
