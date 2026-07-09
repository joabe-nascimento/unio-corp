<?php

namespace App\Twig;

use App\Service\Organismo\OrganismoCopyService;
use App\Service\Organismo\OrganismoFeature;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class OrganismoTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private OrganismoFeature $organismo,
        private OrganismoCopyService $copy,
    ) {
    }

    /** @return array<string, mixed> */
    public function getGlobals(): array
    {
        if (!$this->organismo->isEnabled()) {
            return [
                'organismo' => ['enabled' => false, 'pulso_home' => false, 'copy' => []],
                'org_clinic' => false,
                'org_brand_label' => null,
            ];
        }

        return [
            'organismo' => [
                'enabled' => true,
                'pulso_home' => $this->organismo->isPulsoHome(),
                'copy' => $this->copy->getGlobals(),
            ],
            'org_clinic' => $this->copy->isClinicProfile(),
            'org_brand_label' => $this->copy->brandName(),
        ];
    }
}
