<?php

namespace App\Tests\Service\Platform;

use App\Service\Platform\PlatformOpsLogParser;
use PHPUnit\Framework\TestCase;

final class PlatformOpsLogParserTest extends TestCase
{
    private PlatformOpsLogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PlatformOpsLogParser();
    }

    public function testClassifiesMercureWarningAsIntegration(): void
    {
        $line = '[2026-07-03T12:20:51.644874-03:00] app.WARNING: Chat Mercure publish failed: Failed to send an update. {"message":"Failed to send an update."} []';

        $report = $this->parser->analyzeLines([$line]);

        self::assertSame(1, $report['counts']['integrations']);
        self::assertCount(1, $report['incidents']['integrations']);
        self::assertStringContainsString('Mercure', $report['incidents']['integrations'][0]['message']);
    }

    public function testFiltersChatPollNoise(): void
    {
        $line = '[2026-07-03T12:20:49.094693-03:00] request.INFO: Matched route "app_chat_api_call_poll". {"route":"app_chat_api_call_poll","method":"GET"} []';

        $report = $this->parser->analyzeLines([$line]);

        self::assertSame(1, $report['counts']['noise']);
        self::assertSame(0, $report['counts']['routes']);
    }

    public function testClassifiesDeprecation(): void
    {
        $line = '[2026-07-03T12:20:48.110178-03:00] deprecation.INFO: User Deprecated: Support for MySQL < 8 is deprecated {"exception":"..."} []';

        $report = $this->parser->analyzeLines([$line]);

        self::assertSame(1, $report['counts']['deprecations']);
    }

    public function testClassifiesUncaughtExceptionAsRouteIssue(): void
    {
        $line = '[2026-07-03T10:00:00+00:00] request.CRITICAL: Uncaught PHP Exception Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException: No route found for "GET /foo" [] []';

        $report = $this->parser->analyzeLines([$line]);

        self::assertSame(1, $report['counts']['routes']);
    }
}
