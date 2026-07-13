<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Clinic\ClinicStaffRole;
use App\Security\ProductGrantRouteMap;
use App\Service\Clinic\ClinicStaffAccess;
use Symfony\Component\Process\Process;

$process = new Process(['php', 'bin/console', 'debug:router', '--format=json']);
$process->setWorkingDirectory(dirname(__DIR__));
$process->run();
if (!$process->isSuccessful()) {
    fwrite(STDERR, $process->getErrorOutput() ?: $process->getOutput());
    exit(1);
}

/** @var array<string, mixed> $routes */
$routes = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
$clinic = [];
foreach (array_keys($routes) as $name) {
    if (str_starts_with($name, 'app_pos_operatorio')
        || str_starts_with($name, 'app_organismo')
        || $name === 'app_pulso') {
        $clinic[] = $name;
    }
}
sort($clinic);

$unmapped = [];
foreach ($clinic as $name) {
    if ($name === 'app_pulso' || $name === 'app_pos_operatorio') {
        continue;
    }
    if (!isset(ProductGrantRouteMap::MAP[$name])) {
        $unmapped[] = $name;
    }
}

echo "=== UNMAPPED CLINIC ROUTES (" . count($unmapped) . ") ===\n";
foreach ($unmapped as $r) {
    echo "  $r\n";
}

$checks = [
    ClinicStaffRole::RECEPCAO => [
        'allow' => ['app_pos_operatorio_pacientes', 'app_pos_operatorio_agenda', 'app_pos_operatorio_recepcao', 'app_pos_operatorio_paciente_novo', 'app_pos_operatorio_contas'],
        'deny' => ['app_pos_operatorio_alertas', 'app_pos_operatorio_questionarios', 'app_pos_operatorio_protocolos', 'app_pos_operatorio_config', 'app_pos_operatorio_relatorios'],
    ],
    ClinicStaffRole::ENFERMAGEM => [
        'allow' => ['app_pos_operatorio_questionarios', 'app_pos_operatorio_trabalho', 'app_pos_operatorio_lembretes', 'app_pos_operatorio_portal', 'app_pos_operatorio_paciente_show'],
        'deny' => ['app_pos_operatorio_paciente_novo', 'app_pos_operatorio_contas', 'app_pos_operatorio_alertas', 'app_pos_operatorio_protocolos', 'app_pos_operatorio_config'],
    ],
    ClinicStaffRole::MEDICO => [
        'allow' => ['app_pos_operatorio_alertas', 'app_pos_operatorio_paciente_show', 'app_pos_operatorio_protocolos', 'app_pos_operatorio_sala_critica'],
        'deny' => ['app_pos_operatorio_config', 'app_pos_operatorio_questionarios', 'app_pos_operatorio_relatorios', 'app_pos_operatorio_paciente_novo', 'app_pos_operatorio_contas'],
    ],
    ClinicStaffRole::COORDENACAO => [
        'allow' => ['app_pos_operatorio_relatorios', 'app_pos_operatorio_config', 'app_pos_operatorio_integracoes', 'app_pos_operatorio_produtos', 'app_pos_operatorio_relatorios_export_alertas'],
        'deny' => ['app_pos_operatorio_pacientes', 'app_pos_operatorio_alertas', 'app_pos_operatorio_questionarios', 'app_pos_operatorio_protocolos', 'app_pos_operatorio_contas'],
    ],
];

echo "\n=== MATRIX CHECK ===\n";
$failures = 0;
foreach ($checks as $perfil => $sets) {
    foreach ($sets['allow'] as $route) {
        $ok = ClinicStaffAccess::routeAllowedByPerfil($perfil, $route);
        if (!$ok) {
            echo "FAIL allow $perfil $route\n";
            ++$failures;
        }
    }
    foreach ($sets['deny'] as $route) {
        $ok = ClinicStaffAccess::routeAllowedByPerfil($perfil, $route);
        if ($ok) {
            echo "FAIL deny $perfil $route\n";
            ++$failures;
        }
    }
}

if ($failures === 0) {
    echo "OK matrix expectations\n";
} else {
    echo "FAILURES: $failures\n";
    exit(1);
}

echo "\n=== FEATURE SIDEBAR BY ROLE ===\n";
foreach (ClinicStaffRole::ALL as $perfil) {
    $ids = [];
    foreach (array_keys(ClinicStaffRole::FEATURE_PRODUCT) as $featureId) {
        if (ClinicStaffRole::allowsFeature($perfil, $featureId)) {
            $ids[] = $featureId;
        }
    }
    echo $perfil . ': ' . implode(', ', $ids) . "\n";
}
