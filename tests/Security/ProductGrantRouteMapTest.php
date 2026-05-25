<?php

namespace App\Tests\Security;

use App\Security\ProductGrantRouteMap;
use PHPUnit\Framework\TestCase;

class ProductGrantRouteMapTest extends TestCase
{
    public function testCoreProjetosRoutesAreMapped(): void
    {
        $routes = [
            'app_core_projetos',
            'app_core_projetos_show',
            'app_core_tarefa_nova',
            'app_core_tarefa_editar',
            'app_core_tarefa_excluir',
            'app_core_tarefa_mover',
        ];

        foreach ($routes as $route) {
            self::assertArrayHasKey(
                $route,
                ProductGrantRouteMap::MAP,
                sprintf('Rota %s deve estar em ProductGrantRouteMap', $route),
            );
            self::assertSame('product_core', ProductGrantRouteMap::MAP[$route]['scope']);
            self::assertSame('projetos', ProductGrantRouteMap::MAP[$route]['product']);
        }
    }
}
