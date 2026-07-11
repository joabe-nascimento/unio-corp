<?php

namespace App\Service\Organismo;

use App\Entity\Empresa;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PosOperatorio\PosOperatorioAlertQueueService;
use App\Service\PosOperatorio\PosOperatorioQuestionarioListService;

/** Contadores reais para badges da sidebar e KPIs do dashboard clínica. */
final class ClinicNavBadgeService
{
    public function __construct(
        private PosOperatorioAlertQueueService $alertQueue,
        private PosOperatorioQuestionarioListService $questionarios,
        private PosOperatorioPacienteRepository $pacientes,
    ) {}

    /** @return array{sala_critica: int, alertas: int, questionarios: int, pacientes_ativos: int} */
    public function forEmpresa(?Empresa $empresa): array
    {
        if ($empresa === null) {
            return ['sala_critica' => 0, 'alertas' => 0, 'questionarios' => 0, 'pacientes_ativos' => 0];
        }

        $queue = $this->alertQueue->buildQueue($empresa);
        $stats = $queue['stats'];
        $qStats = $this->questionarios->buildList($empresa, 1)['stats'];

        return [
            'sala_critica' => $stats['p1'],
            'alertas' => $stats['abertos'] + $stats['em_atendimento'],
            'questionarios' => $qStats['pendentes'],
            'pacientes_ativos' => $this->pacientes->countAtivosByEmpresa($empresa),
        ];
    }
}
