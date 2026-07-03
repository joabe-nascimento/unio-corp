<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioPaciente;
use App\Service\Vitoria\VitoriaClient;

/**
 * Motor de triagem clínica — delega ao serviço Vitória com fallback local.
 */
final class PosOperatorioTriageService
{
    public function __construct(
        private VitoriaClient $vitoria,
    ) {}

    /**
     * @param array<string, mixed> $respostas
     *
     * @return array{prioridade: string, score_risco: int, motivo: string, acoes_sugeridas: list<string>, requer_contato_imediato: bool, source: string}
     */
    public function evaluate(PosOperatorioPaciente $paciente, array $respostas): array
    {
        $protocolo = $paciente->getProtocolo();
        $regras = $protocolo?->getRegrasAlerta() ?? [];

        $remote = $this->vitoria->evaluateTriage(
            $respostas,
            $paciente->getCodigo(),
            $paciente->getProcedimento(),
            $paciente->getDiaPosOperatorio(),
            $regras,
        );

        if ($remote !== null) {
            return [
                'prioridade' => (string) ($remote['prioridade'] ?? 'P4'),
                'score_risco' => (int) ($remote['score_risco'] ?? 0),
                'motivo' => (string) ($remote['motivo'] ?? 'Avaliação clínica'),
                'acoes_sugeridas' => $remote['acoes_sugeridas'] ?? [],
                'requer_contato_imediato' => (bool) ($remote['requer_contato_imediato'] ?? false),
                'source' => (string) ($remote['source'] ?? 'vitoria'),
            ];
        }

        return $this->evaluateLocal($respostas, $paciente);
    }

    /**
     * @param array<string, mixed> $respostas
     *
     * @return array{prioridade: string, score_risco: int, motivo: string, acoes_sugeridas: list<string>, requer_contato_imediato: bool, source: string}
     */
    private function evaluateLocal(array $respostas, PosOperatorioPaciente $paciente): array
    {
        $score = 0;
        $motivos = [];
        $prioridade = 'P4';
        $imediato = false;

        $dor = (float) ($respostas['dor'] ?? $respostas['nivel_dor'] ?? 0);
        $febre = (float) ($respostas['febre'] ?? $respostas['temperatura'] ?? 0);

        if ($dor >= 8) {
            $score += 40;
            $motivos[] = sprintf('Dor intensa (%d/10)', (int) $dor);
            $prioridade = 'P1';
            $imediato = true;
        } elseif ($dor >= 6) {
            $score += 25;
            $motivos[] = sprintf('Dor moderada (%d/10)', (int) $dor);
            $prioridade = 'P2';
        }

        if ($febre >= 38.5) {
            $score += 30;
            $motivos[] = sprintf('Febre alta (%.1f°C)', $febre);
            if ($prioridade !== 'P1') {
                $prioridade = 'P2';
            }
            $dia = $paciente->getDiaPosOperatorio();
            if ($dia !== null && $dia <= 3) {
                $prioridade = 'P1';
                $imediato = true;
            }
        }

        $score = min(100, max(0, $score));
        if ($motivos === []) {
            $motivos[] = 'Evolução dentro do esperado';
        }

        return [
            'prioridade' => $prioridade,
            'score_risco' => $score,
            'motivo' => implode('; ', $motivos),
            'acoes_sugeridas' => ['Monitorar evolução', 'Registrar na linha do tempo'],
            'requer_contato_imediato' => $imediato,
            'source' => 'local',
        ];
    }
}
