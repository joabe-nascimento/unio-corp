<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoProcesso;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\JuridicoProcessoTarefaRepository;

/**
 * Modelo heurístico de previsão de êxito — combina histórico real da carteira (taxa de
 * êxito por área), fase processual, saúde de execução (tarefas em dia) e tempo de
 * tramitação para estimar a probabilidade de resultado favorável.
 *
 * Importante: é um score determinístico e 100% explicável (soma de fatores conhecidos),
 * não um modelo de machine learning treinado — cada fator é auditável pelo advogado.
 *
 * @phpstan-type FatorPrevisao array{label: string, peso: int, favoravel: bool, detalhe: string}
 */
final class PrevisaoExitoService
{
    private const BASE = 50;

    public function __construct(
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoProcessoTarefaRepository $tarefaRepo,
    ) {
    }

    /**
     * @return array{score: int, nivel: string, label: string, cor: string, fatores: list<FatorPrevisao>}
     */
    public function prever(JuridicoProcesso $processo): array
    {
        $empresa = $processo->getEmpresa();
        $fatores = [];
        $score = self::BASE;

        $score += $this->fatorHistoricoArea($processo, $empresa, $fatores);
        $score += $this->fatorFase($processo, $fatores);
        $score += $this->fatorTarefas($processo, $fatores);
        $score += $this->fatorTempoTramitacao($processo, $fatores);
        $score += $this->fatorValorCausa($processo, $fatores);

        $score = max(5, min(95, $score));

        return [
            'score' => $score,
            'nivel' => $this->nivel($score),
            'label' => $this->labelNivel($score),
            'cor' => $this->corNivel($score),
            'fatores' => $fatores,
        ];
    }

    /** @param list<FatorPrevisao> $fatores */
    private function fatorHistoricoArea(JuridicoProcesso $processo, Empresa $empresa, array &$fatores): int
    {
        $historico = $this->processoRepo->taxaExitoPorArea($empresa);
        $area = $processo->getArea() !== null && $processo->getArea() !== '' ? $processo->getArea() : 'geral';
        $dados = $historico[$area] ?? null;

        if ($dados === null || $dados['total'] < 3) {
            $fatores[] = [
                'label' => 'Histórico da área',
                'peso' => 0,
                'favoravel' => true,
                'detalhe' => 'Ainda não há histórico suficiente (mín. 3 casos encerrados) nesta área para influenciar o score.',
            ];

            return 0;
        }

        $delta = (int) round(($dados['taxa'] - 50) * 0.4);
        $fatores[] = [
            'label' => 'Histórico da área "' . $area . '"',
            'peso' => $delta,
            'favoravel' => $delta >= 0,
            'detalhe' => sprintf('%.1f%% de êxito em %d caso(s) encerrado(s) nesta área no escritório.', $dados['taxa'], $dados['total']),
        ];

        return $delta;
    }

    /** @param list<FatorPrevisao> $fatores */
    private function fatorFase(JuridicoProcesso $processo, array &$fatores): int
    {
        $pesos = [
            JuridicoProcesso::FASE_CONHECIMENTO => 0,
            JuridicoProcesso::FASE_INSTRUCAO => 3,
            JuridicoProcesso::FASE_SENTENCA => 6,
            JuridicoProcesso::FASE_RECURSAL => -4,
            JuridicoProcesso::FASE_EXECUCAO => 12,
            JuridicoProcesso::FASE_ENCERRADO => 0,
        ];
        $labels = [
            JuridicoProcesso::FASE_CONHECIMENTO => 'fase de conhecimento (neutro)',
            JuridicoProcesso::FASE_INSTRUCAO => 'em instrução — provas favorecem avanço',
            JuridicoProcesso::FASE_SENTENCA => 'aguardando sentença — tese já consolidada nos autos',
            JuridicoProcesso::FASE_RECURSAL => 'em fase recursal — risco de reversão',
            JuridicoProcesso::FASE_EXECUCAO => 'em execução — mérito já decidido a favor',
            JuridicoProcesso::FASE_ENCERRADO => 'processo encerrado',
        ];
        $peso = $pesos[$processo->getFase()] ?? 0;

        $fatores[] = [
            'label' => 'Fase processual',
            'peso' => $peso,
            'favoravel' => $peso >= 0,
            'detalhe' => ucfirst($labels[$processo->getFase()] ?? $processo->getFase()) . '.',
        ];

        return $peso;
    }

    /** @param list<FatorPrevisao> $fatores */
    private function fatorTarefas(JuridicoProcesso $processo, array &$fatores): int
    {
        $tarefas = $this->tarefaRepo->findForProcesso($processo);
        $atrasadas = 0;
        $agora = new \DateTimeImmutable();
        foreach ($tarefas as $tarefa) {
            if (!$tarefa->isConcluida() && $tarefa->getPrazo() !== null && $tarefa->getPrazo() < $agora) {
                ++$atrasadas;
            }
        }

        if ($atrasadas === 0) {
            $fatores[] = [
                'label' => 'Execução do caso',
                'peso' => 2,
                'favoravel' => true,
                'detalhe' => 'Nenhuma tarefa atrasada — acompanhamento em dia.',
            ];

            return 2;
        }

        $peso = -min(15, $atrasadas * 5);
        $fatores[] = [
            'label' => 'Execução do caso',
            'peso' => $peso,
            'favoravel' => false,
            'detalhe' => sprintf('%d tarefa(s) atrasada(s) — risco de perda de prazo ou prova.', $atrasadas),
        ];

        return $peso;
    }

    /** @param list<FatorPrevisao> $fatores */
    private function fatorTempoTramitacao(JuridicoProcesso $processo, array &$fatores): int
    {
        $dias = $processo->getCriadoEm()->diff(new \DateTimeImmutable())->days ?? 0;
        $anos = $dias / 365;

        if ($anos <= 2) {
            $fatores[] = [
                'label' => 'Tempo de tramitação',
                'peso' => 1,
                'favoravel' => true,
                'detalhe' => sprintf('%.1f ano(s) de tramitação — dentro da média.', $anos),
            ];

            return 1;
        }

        $peso = (int) -min(8, round(($anos - 2) * 2));
        $fatores[] = [
            'label' => 'Tempo de tramitação',
            'peso' => $peso,
            'favoravel' => false,
            'detalhe' => sprintf('%.1f ano(s) de tramitação — processos muito longos tendem a perder força probatória.', $anos),
        ];

        return $peso;
    }

    /** @param list<FatorPrevisao> $fatores */
    private function fatorValorCausa(JuridicoProcesso $processo, array &$fatores): int
    {
        $valor = $processo->getValor() !== null ? (float) $processo->getValor() : 0.0;
        if ($valor <= 0) {
            return 0;
        }

        if ($valor >= 300000.0) {
            $fatores[] = [
                'label' => 'Complexidade pelo valor da causa',
                'peso' => -2,
                'favoravel' => false,
                'detalhe' => 'Valor elevado costuma atrair mais recursos da parte contrária.',
            ];

            return -2;
        }

        return 0;
    }

    private function nivel(int $score): string
    {
        return match (true) {
            $score >= 70 => 'alto',
            $score >= 45 => 'medio',
            default => 'baixo',
        };
    }

    private function labelNivel(int $score): string
    {
        return match ($this->nivel($score)) {
            'alto' => 'Probabilidade alta',
            'medio' => 'Probabilidade moderada',
            default => 'Probabilidade baixa',
        };
    }

    private function corNivel(int $score): string
    {
        return match ($this->nivel($score)) {
            'alto' => '#2fbf71',
            'medio' => '#e8a33d',
            default => '#e05260',
        };
    }
}
