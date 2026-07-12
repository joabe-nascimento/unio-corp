<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioPaciente;
use App\Service\Vitoria\VitoriaClient;

/**
 * Motor de triagem clínica — Vitória com fallback local.
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
        $regras = $protocolo?->getRegrasAlerta() ?? PosOperatorioProtocoloDefaults::regrasAlerta();

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

        return PosOperatorioLocalTriage::evaluate($respostas, $paciente, $regras);
    }
}
