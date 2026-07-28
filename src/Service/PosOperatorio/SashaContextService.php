<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\Organismo\Memory\OrganismMemoryQuery;

/**
 * Monta contexto clínico para a Sasha (chat com paciente em foco).
 */
final class SashaContextService
{
    public function __construct(
        private PosOperatorioPacienteRepository $pacienteRepo,
        private PosOperatorioAlertaRepository $alertaRepo,
        private OrganismMemoryQuery $memory,
    ) {}

    public function findPaciente(Empresa $empresa, ?string $codigo): ?PosOperatorioPaciente
    {
        if ($codigo === null || trim($codigo) === '') {
            return null;
        }

        return $this->pacienteRepo->findByCodigo($empresa, trim($codigo));
    }

    /**
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    public function enrichChatContext(Empresa $empresa, array $base, ?string $pacienteCodigo): array
    {
        $paciente = $this->findPaciente($empresa, $pacienteCodigo);
        if ($paciente === null) {
            return $base;
        }

        $ultima = $paciente->getUltimaResposta();
        $alertasAbertos = $this->alertaRepo->countAbertosByPaciente($paciente);

        $base['hub'] = $base['hub'] ?? 'hub_pos_operatorio';
        $base['patient_codigo'] = $paciente->getCodigo();
        $base['extra'] = array_merge($base['extra'] ?? [], [
            'paciente_nome' => $paciente->getNome(),
            'procedimento' => $paciente->getProcedimento(),
            'dia_pos_operatorio' => $paciente->getDiaPosOperatorio(),
            'status' => $paciente->getStatus(),
            'alertas_abertos' => $alertasAbertos,
            'ultimo_score_risco' => $ultima?->getScoreRisco(),
            'ultima_resposta_em' => $ultima?->getRespondidoEm()->format(\DateTimeInterface::ATOM),
            'organismo_memoria' => array_map(
                static fn (array $f): string => $f['sujeito'],
                $this->memory->forPaciente($paciente, 4),
            ),
            'clinical_summary' => sprintf(
                'Paciente %s (%s), %s, D+%s, status %s, %d alerta(s) aberto(s), último score %s.',
                $paciente->getNome(),
                $paciente->getCodigo(),
                $paciente->getProcedimento() ?? 'procedimento não informado',
                $paciente->getDiaPosOperatorio() ?? '?',
                $paciente->getStatus(),
                $alertasAbertos,
                $ultima ? (string) $ultima->getScoreRisco() : 'n/a',
            ),
        ]);

        return $base;
    }
}
