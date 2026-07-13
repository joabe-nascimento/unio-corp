<?php

namespace App\Tests\Clinic;

use App\Clinic\ClinicStaffRole;
use App\Service\Clinic\ClinicStaffAccess;
use PHPUnit\Framework\TestCase;

final class ClinicStaffRoleTest extends TestCase
{
    public function testMatrixMatchesOperationalProfiles(): void
    {
        self::assertTrue(ClinicStaffRole::allowsProduct(ClinicStaffRole::RECEPCAO, 'pacientes'));
        self::assertTrue(ClinicStaffRole::allowsProduct(ClinicStaffRole::RECEPCAO, 'operacao'));
        self::assertFalse(ClinicStaffRole::allowsProduct(ClinicStaffRole::RECEPCAO, 'alertas'));

        self::assertTrue(ClinicStaffRole::allowsProduct(ClinicStaffRole::ENFERMAGEM, 'questionarios'));
        self::assertTrue(ClinicStaffRole::allowsProduct(ClinicStaffRole::ENFERMAGEM, 'pacientes'));
        self::assertFalse(ClinicStaffRole::allowsProduct(ClinicStaffRole::ENFERMAGEM, 'operacao'));
        self::assertFalse(ClinicStaffRole::allowsProduct(ClinicStaffRole::ENFERMAGEM, 'configuracoes'));

        self::assertTrue(ClinicStaffRole::allowsProduct(ClinicStaffRole::MEDICO, 'alertas'));
        self::assertTrue(ClinicStaffRole::allowsProduct(ClinicStaffRole::MEDICO, 'protocolos'));
        self::assertTrue(ClinicStaffRole::allowsFeature(ClinicStaffRole::MEDICO, 'pacientes'));
        self::assertFalse(ClinicStaffRole::allowsFeature(ClinicStaffRole::MEDICO, 'contas'));
        self::assertFalse(ClinicStaffRole::allowsFeature(ClinicStaffRole::MEDICO, 'recepcao'));

        self::assertTrue(ClinicStaffRole::allowsProduct(ClinicStaffRole::COORDENACAO, 'relatorios'));
        self::assertTrue(ClinicStaffRole::allowsProduct(ClinicStaffRole::COORDENACAO, 'configuracoes'));
        self::assertFalse(ClinicStaffRole::allowsProduct(ClinicStaffRole::COORDENACAO, 'pacientes'));
    }

    public function testRouteMatrixForStaffRoles(): void
    {
        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::RECEPCAO, 'app_pos_operatorio_pacientes'));
        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::RECEPCAO, 'app_pos_operatorio_agenda'));
        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::RECEPCAO, 'app_pos_operatorio_paciente_novo'));
        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::RECEPCAO, 'app_pos_operatorio_contas'));
        self::assertFalse(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::RECEPCAO, 'app_pos_operatorio_alertas'));
        self::assertFalse(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::RECEPCAO, 'app_pos_operatorio_config'));

        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::ENFERMAGEM, 'app_pos_operatorio_questionarios'));
        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::ENFERMAGEM, 'app_pos_operatorio_paciente_show'));
        self::assertFalse(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::ENFERMAGEM, 'app_pos_operatorio_paciente_novo'));
        self::assertFalse(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::ENFERMAGEM, 'app_pos_operatorio_contas'));
        self::assertFalse(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::ENFERMAGEM, 'app_pos_operatorio_protocolos'));

        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::MEDICO, 'app_pos_operatorio_alertas'));
        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::MEDICO, 'app_pos_operatorio_paciente_show'));
        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::MEDICO, 'app_pos_operatorio_protocolos'));
        self::assertFalse(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::MEDICO, 'app_pos_operatorio_paciente_novo'));
        self::assertFalse(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::MEDICO, 'app_pos_operatorio_contas'));
        self::assertFalse(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::MEDICO, 'app_pos_operatorio_config'));

        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::COORDENACAO, 'app_pos_operatorio_relatorios'));
        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::COORDENACAO, 'app_pos_operatorio_config'));
        self::assertFalse(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::COORDENACAO, 'app_pos_operatorio_pacientes'));

        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::RECEPCAO, 'app_pulso'));
        self::assertTrue(ClinicStaffAccess::routeAllowedByPerfil(ClinicStaffRole::RECEPCAO, 'app_pos_operatorio'));
    }

    public function testLegacyWorkProfilesAreNotClinicProducts(): void
    {
        foreach (['GESTOR', 'GESTOR_EQUIPE', 'SUPERVISOR', 'SUPERVISOR_EQUIPE', 'MEMBRO'] as $legacy) {
            self::assertFalse(ClinicStaffRole::isClinicStaffPerfil($legacy));
            self::assertSame([], ClinicStaffRole::productsFor($legacy));
        }
    }

    public function testHomeRoutesByRole(): void
    {
        self::assertSame('app_pos_operatorio_pacientes', ClinicStaffRole::homeRoute(ClinicStaffRole::RECEPCAO));
        self::assertSame('app_pos_operatorio_questionarios', ClinicStaffRole::homeRoute(ClinicStaffRole::ENFERMAGEM));
        self::assertSame('app_pos_operatorio_alertas', ClinicStaffRole::homeRoute(ClinicStaffRole::MEDICO));
        self::assertSame('app_pos_operatorio_relatorios', ClinicStaffRole::homeRoute(ClinicStaffRole::COORDENACAO));
    }
}
