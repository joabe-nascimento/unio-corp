<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioPaciente;

/** Fallback local de triagem (sem Vitória). */
final class PosOperatorioLocalTriage
{
    /**
     * @param array<string, mixed> $respostas
     * @param array<string, mixed> $regras
     *
     * @return array{prioridade: string, score_risco: int, motivo: string, acoes_sugeridas: list<string>, requer_contato_imediato: bool, source: string}
     */
    public static function evaluate(array $respostas, PosOperatorioPaciente $paciente, array $regras = []): array
    {
        if ($regras === []) {
            $regras = PosOperatorioProtocoloDefaults::regrasAlerta();
        }

        $score = 0;
        $motivos = [];
        $prioridade = 'P4';
        $imediato = false;

        $dorP1 = (float) ($regras['dor_p1_min'] ?? 8);
        $dorP2 = (float) ($regras['dor_p2_min'] ?? 6);
        $febreP2 = (float) ($regras['febre_p2_min'] ?? 38.5);

        $dor = (float) ($respostas['dor'] ?? $respostas['nivel_dor'] ?? 0);
        $febre = (float) ($respostas['febre'] ?? $respostas['temperatura'] ?? 0);

        if ($dor >= $dorP1) {
            $score += 40;
            $motivos[] = sprintf('Dor intensa (%d/10)', (int) $dor);
            $prioridade = 'P1';
            $imediato = true;
        } elseif ($dor >= $dorP2) {
            $score += 25;
            $motivos[] = sprintf('Dor moderada (%d/10)', (int) $dor);
            $prioridade = 'P2';
        }

        if ($febre >= $febreP2) {
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

        $nausea = mb_strtolower((string) ($respostas['nausea'] ?? ''));
        if (\in_array($nausea, ['sim', '1', 'true', 'leve', 'persistente'], true)) {
            $score += $nausea === 'persistente' ? 20 : 15;
            $motivos[] = $nausea === 'persistente'
                ? 'Náusea ou vômito persistente'
                : 'Náusea ou vômito reportado';
            if ($prioridade === 'P4') {
                $prioridade = 'P3';
            }
            if ($nausea === 'persistente' && $prioridade !== 'P1') {
                $prioridade = 'P2';
            }
        }

        $sangramento = mb_strtolower((string) ($respostas['sangramento'] ?? $respostas['curativo'] ?? ''));
        if ($sangramento === 'intenso') {
            $score += 45;
            $motivos[] = 'Sangramento intenso no curativo';
            $prioridade = 'P1';
            $imediato = true;
        } elseif ($sangramento === 'leve') {
            $score += 20;
            $motivos[] = 'Sangramento leve no curativo';
            if ($prioridade === 'P4') {
                $prioridade = 'P3';
            } elseif ($prioridade === 'P3') {
                $prioridade = 'P2';
            }
        }

        $obsPaciente = mb_strtolower((string) ($paciente->getObservacoes() ?? ''));
        $obsResposta = mb_strtolower((string) ($respostas['observacao'] ?? $respostas['obs'] ?? ''));
        $obs = trim($obsPaciente . ' ' . $obsResposta);
        if ($obs !== '') {
            foreach (['anticoagul', 'idoso', 'diabetes', 'cardiopat', 'imunossup'] as $term) {
                if (str_contains($obs, $term)) {
                    $score += 10;
                    $motivos[] = 'Perfil de risco clínico (observações)';
                    if ($prioridade === 'P4') {
                        $prioridade = 'P3';
                    }
                    break;
                }
            }
        }

        $score = min(100, max(0, $score));
        if ($motivos === []) {
            $motivos[] = 'Evolução dentro do esperado';
        }

        $acoes = ['Monitorar evolução', 'Registrar na linha do tempo'];
        if ($imediato) {
            array_unshift($acoes, 'Contato imediato com a equipe');
        }

        return [
            'prioridade' => $prioridade,
            'score_risco' => $score,
            'motivo' => implode('; ', $motivos),
            'acoes_sugeridas' => $acoes,
            'requer_contato_imediato' => $imediato,
            'source' => 'local',
        ];
    }
}
