<?php

namespace App\Service\Organismo;

use App\Service\Marketing\StudioLandingService;

/** Módulos da plataforma base para sidebar e cards (sem produtos clínicos). */
final class StudioModuleNavService
{
    private const CLINICAL_IDS = ['pos-operatorio', 'pos_operatorio', 'saude_ocupacional', 'sst'];

    public function __construct(
        private StudioLandingService $landing,
    ) {
    }

    /** @return list<array{id: string, label: string, desc: string, icon: string, route: string}> */
    public function forSidebar(int $limit = 6): array
    {
        $modules = [];
        foreach ($this->landing->hubs() as $hub) {
            $id = (string) ($hub['id'] ?? '');
            if ($id === '' || \in_array($id, self::CLINICAL_IDS, true)) {
                continue;
            }
            $route = (string) ($hub['route'] ?? '');
            if ($route === '') {
                continue;
            }
            $modules[] = [
                'id' => $id,
                'label' => (string) ($hub['label'] ?? $id),
                'desc' => (string) ($hub['desc'] ?? ''),
                'icon' => (string) ($hub['icon'] ?? 'fa-cube'),
                'route' => $route,
            ];
            if (\count($modules) >= $limit) {
                break;
            }
        }

        return $modules;
    }
}
