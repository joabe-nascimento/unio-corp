<?php

namespace App\Tests\Service\Ti;

use PHPUnit\Framework\TestCase;

class TiChamadoServiceAllSortedTest extends TestCase
{
    public function testPriorityThenOpenedAtSortOrder(): void
    {
        $tickets = [
            ['id' => 'TK-0002', 'priority' => 'P3', 'opened_at' => '2026-06-07 12:00:00'],
            ['id' => 'TK-0001', 'priority' => 'P1', 'opened_at' => '2026-06-07 10:00:00'],
            ['id' => 'TK-0003', 'priority' => 'P1', 'opened_at' => '2026-06-06 09:00:00'],
        ];

        $sorted = $this->sortLikeService($tickets);

        self::assertSame(['TK-0001', 'TK-0003', 'TK-0002'], array_column($sorted, 'id'));
    }

    /** @param list<array<string, mixed>> $tickets
     * @return list<array<string, mixed>>
     */
    private function sortLikeService(array $tickets): array
    {
        $priorityOrder = ['P1' => 1, 'P2' => 2, 'P3' => 3, 'P4' => 4];
        usort($tickets, static function (array $a, array $b) use ($priorityOrder): int {
            $pa = $priorityOrder[$a['priority'] ?? 'P4'] ?? 5;
            $pb = $priorityOrder[$b['priority'] ?? 'P4'] ?? 5;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp($b['opened_at'] ?? '', $a['opened_at'] ?? '');
        });

        return $tickets;
    }
}
