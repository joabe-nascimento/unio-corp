<?php

declare(strict_types=1);

namespace App\Tests\Clinic;

use App\Clinic\ClinicScopedRoutes;
use PHPUnit\Framework\TestCase;

final class ClinicScopedRoutesTest extends TestCase
{
    public function testPublicBeneficiaryPathsAreRestricted(): void
    {
        self::assertTrue(ClinicScopedRoutes::isRestricted(null, '/verificar/ABC123'));
        self::assertTrue(ClinicScopedRoutes::isRestricted(null, '/carteirinha-digital'));
        self::assertTrue(ClinicScopedRoutes::isRestricted(null, '/comprovante-procedimento'));
        self::assertTrue(ClinicScopedRoutes::isRestricted(null, '/guia-medico'));
        self::assertTrue(ClinicScopedRoutes::isRestricted(null, '/paciente'));
        self::assertTrue(ClinicScopedRoutes::isRestricted(null, '/wallet/apple/carteirinha/token.pkpass'));
    }

    public function testStudioPathsStayOpen(): void
    {
        self::assertFalse(ClinicScopedRoutes::isRestricted('app_home', '/'));
        self::assertFalse(ClinicScopedRoutes::isRestricted('app_dashboard', '/dashboard'));
        self::assertFalse(ClinicScopedRoutes::isRestricted('app_pos_operatorio_trabalho', '/pos-operatorio/trabalho'));
    }

    public function testClinicMarketingModulePathsAreRestricted(): void
    {
        self::assertTrue(ClinicScopedRoutes::isRestricted('app_marketing_modulo_show', '/modulo/carteirinha-digital'));
        self::assertTrue(ClinicScopedRoutes::isRestricted('api_marketing_modulo_pulso', '/api/modulo/guia-medico/pulso'));
    }
}
