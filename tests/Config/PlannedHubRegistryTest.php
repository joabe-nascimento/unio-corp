<?php

namespace App\Tests\Config;

use App\Config\PlannedHubRegistry;
use PHPUnit\Framework\TestCase;

class PlannedHubRegistryTest extends TestCase
{
    public function testEveryHubHasGroup(): void
    {
        foreach (PlannedHubRegistry::HUBS as $hub) {
            self::assertArrayHasKey(
                $hub['id'],
                PlannedHubRegistry::HUB_GROUP,
                sprintf('Hub %s precisa de entrada em HUB_GROUP', $hub['id']),
            );
            self::assertArrayHasKey(
                PlannedHubRegistry::HUB_GROUP[$hub['id']],
                PlannedHubRegistry::GROUP_LABELS,
            );
        }
    }

    public function testGroupHubsPreservesAllItems(): void
    {
        $grouped = PlannedHubRegistry::groupHubs(PlannedHubRegistry::HUBS);
        $count = 0;
        foreach ($grouped as $group) {
            $count += \count($group['hubs']);
            self::assertNotSame('', $group['label']);
        }

        self::assertSame(\count(PlannedHubRegistry::HUBS), $count);
    }

    public function testFindByRouteMatchesLongestPrefixFirstIsStable(): void
    {
        self::assertSame('publicidade', PlannedHubRegistry::findByRoute('app_publicidade_campanhas')['id'] ?? null);
        self::assertSame('obras', PlannedHubRegistry::findByRoute('app_engenharia_projetos')['id'] ?? null);
    }
}
