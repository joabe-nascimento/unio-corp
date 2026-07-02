<?php

namespace App\Tests\Config;

use App\Config\HubMaturity;
use App\Config\NotificationPolicyMatrix;
use PHPUnit\Framework\TestCase;

final class Phase15FoundationTest extends TestCase
{
    public function testHubMaturityEnrichesHub(): void
    {
        $hub = HubMaturity::enrichHub(['id' => 'pos_operatorio', 'label' => 'Pós-Op']);
        self::assertSame('mvp', $hub['maturity_level']);
        self::assertSame('MVP', $hub['maturity_label']);
    }

    public function testNotificationPolicyResolvesAlerta(): void
    {
        $n = NotificationPolicyMatrix::resolveNotification('pos_operatorio.alerta_gerado', [
            'prioridade' => 'P1',
            'codigo' => 'PO-1040',
        ]);
        self::assertNotNull($n);
        self::assertSame('danger', $n['severidade']);
        self::assertStringContainsString('PO-1040', $n['titulo']);
    }
}
