<?php

namespace App\Tests\Service;

use App\Service\PlatformNotificationPresenter;
use PHPUnit\Framework\TestCase;

class PlatformNotificationPresenterTest extends TestCase
{
    public function testRelativeTimeNow(): void
    {
        $at = new \DateTimeImmutable('-30 seconds');
        self::assertSame('Agora', PlatformNotificationPresenter::relativeTime($at));
    }

    public function testRelativeTimeMinutes(): void
    {
        $at = new \DateTimeImmutable('-15 minutes');
        self::assertSame('Há 15 min', PlatformNotificationPresenter::relativeTime($at));
    }

    public function testRelativeTimeYesterday(): void
    {
        $at = new \DateTimeImmutable('-26 hours');
        self::assertSame('Ontem', PlatformNotificationPresenter::relativeTime($at));
    }
}
