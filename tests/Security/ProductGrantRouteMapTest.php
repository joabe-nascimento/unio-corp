<?php

namespace App\Tests\Security;

use App\Security\ProductGrantRouteMap;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

class ProductGrantRouteMapTest extends KernelTestCase
{
    public function testAllMappedRoutesExistInRouter(): void
    {
        self::bootKernel();
        $router = static::getContainer()->get(RouterInterface::class);

        foreach (array_keys(ProductGrantRouteMap::MAP) as $routeName) {
            self::assertTrue(
                $router->getRouteCollection()->get($routeName) !== null,
                sprintf('Rota %s está em ProductGrantRouteMap mas não existe no router', $routeName),
            );
        }
    }

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

    public function testRemovedMercureTokenRouteIsNotMapped(): void
    {
        self::assertArrayNotHasKey('app_core_kanban_mercure_token', ProductGrantRouteMap::MAP);
    }
}
