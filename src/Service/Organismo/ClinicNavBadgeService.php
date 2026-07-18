<?php

namespace App\Service\Organismo;

use App\Entity\Empresa;
use App\Repository\ClinicAgendaSolicitacaoRepository;
use App\Repository\ClinicAssinaturaDocumentoRepository;
use App\Repository\ClinicTarefaRepository;
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
        private ClinicAgendaSolicitacaoRepository $solicitacoes,
        private ClinicAssinaturaDocumentoRepository $assinaturas,
        private ClinicTarefaRepository $tarefas,
    ) {}

    /** @return array{sala_critica: int, alertas: int, questionarios: int, pacientes_ativos: int, pacientes_alerta: int, precisa_acao: int, solicitacoes: int, assinaturas: int, tarefas: int} */
    public function forEmpresa(?Empresa $empresa): array
    {
        if ($empresa === null) {
            return [
                'sala_critica' => 0,
                'alertas' => 0,
                'questionarios' => 0,
                'pacientes_ativos' => 0,
                'pacientes_alerta' => 0,
                'precisa_acao' => 0,
                'solicitacoes' => 0,
                'assinaturas' => 0,
                'tarefas' => 0,
            ];
        }

        $queue = $this->alertQueue->buildQueue($empresa);
        $stats = $queue['stats'];
        $qStats = $this->questionarios->buildList($empresa, 1)['stats'];
        $pacientesAlerta = $this->pacientes->countByStatus($empresa, \App\Entity\PosOperatorioPaciente::STATUS_ALERTA);
        $precisaAcao = $stats['p1'] + $qStats['pendentes'];

        return [
            'sala_critica' => $stats['p1'],
            'alertas' => $stats['abertos'] + $stats['em_atendimento'],
            'questionarios' => $qStats['pendentes'],
            'pacientes_ativos' => $this->pacientes->countAtivosByEmpresa($empresa),
            'pacientes_alerta' => $pacientesAlerta,
            'precisa_acao' => $precisaAcao,
            'solicitacoes' => $this->solicitacoes->countPendingByEmpresa($empresa),
            'assinaturas' => $this->assinaturas->countOpenByEmpresa($empresa),
            'tarefas' => $this->tarefas->countPendingByEmpresa($empresa),
        ];
    }
}
