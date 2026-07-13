<?php

namespace App\Service\Organismo\Twin;

use App\Entity\Empresa;
use App\Service\Clinic\ClinicDayPanelService;

final class DayTwinBuilder
{
    public function __construct(
        private ClinicDayPanelService $dayPanel,
        private CascadePredictor $predictor,
    ) {
    }

    /**
     * @return array{
     *   painel: array<string, mixed>,
     *   scenarios: list<array<string, mixed>>,
     *   top: list<array<string, mixed>>
     * }
     */
    public function build(Empresa $empresa, bool $persist = true): array
    {
        $painel = $this->dayPanel->build($empresa);
        $scenarios = $this->predictor->predict($empresa, $persist);

        return [
            'painel' => $painel,
            'scenarios' => $scenarios,
            'top' => \array_slice($scenarios, 0, 3),
        ];
    }
}
