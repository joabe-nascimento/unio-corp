<?php

namespace App\Tests\Service;

use App\Service\PageBackResolver;
use PHPUnit\Framework\TestCase;

class PageBackResolverTest extends TestCase
{
    private PageBackResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PageBackResolver();
    }

    public function testFeriasListBackToRhHub(): void
    {
        $back = $this->resolver->resolve('app_rh_ferias');
        $this->assertNotNull($back);
        $this->assertSame('app_rh', $back['route']);
    }

    public function testFeriasShowBackToList(): void
    {
        $back = $this->resolver->resolve('app_rh_ferias_show');
        $this->assertNotNull($back);
        $this->assertSame('app_rh_ferias', $back['route']);
    }

    public function testRhHubBackToOperacoes(): void
    {
        $back = $this->resolver->resolve('app_rh');
        $this->assertNotNull($back);
        $this->assertSame('app_hub_operacoes', $back['route']);
    }

    public function testPortalSubpageBackToPortal(): void
    {
        $back = $this->resolver->resolve('app_rh_portal_ferias');
        $this->assertNotNull($back);
        $this->assertSame('app_rh_portal', $back['route']);
    }
}
