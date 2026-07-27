<?php

namespace App\Service\Juridico;

/**
 * Calculadora de honorários advocatícios com base em tabela progressiva
 * (referência: tabelas de honorários mínimos OAB, faixas por valor da causa)
 * e honorários de êxito (percentual sobre o proveito econômico).
 *
 * Não substitui a tabela oficial da seccional do escritório — é uma estimativa
 * de referência rápida para orçar propostas e negociações.
 */
final class HonorariosCalculator
{
    /**
     * Faixas progressivas de honorários de consultoria/contencioso por valor da causa.
     * Cada faixa define um percentual aplicado à parcela do valor que cai naquela faixa
     * (lógica de imposto de renda — progressiva, não "tudo pela maior alíquota").
     *
     * @var list<array{ate: float|null, percentual: float}>
     */
    private const FAIXAS = [
        ['ate' => 10_000.0, 'percentual' => 20.0],
        ['ate' => 50_000.0, 'percentual' => 15.0],
        ['ate' => 200_000.0, 'percentual' => 12.0],
        ['ate' => 1_000_000.0, 'percentual' => 8.0],
        ['ate' => null, 'percentual' => 5.0],
    ];

    private const HONORARIO_MINIMO = 1500.0;

    /**
     * @return array{
     *     valor_causa: float,
     *     honorario_contratual_estimado: float,
     *     honorario_exito: float,
     *     percentual_exito: float,
     *     honorario_total_estimado: float,
     *     faixas_aplicadas: list<array{faixa: string, percentual: float, valor: float}>,
     *     explicacao: string
     * }
     */
    public function calcular(float $valorCausa, float $percentualExito = 0.0, bool $aplicarMinimo = true): array
    {
        $valorCausa = max(0.0, $valorCausa);
        $percentualExito = max(0.0, min(100.0, $percentualExito));

        [$contratual, $faixasAplicadas] = $this->calcularProgressivo($valorCausa);

        if ($aplicarMinimo && $contratual < self::HONORARIO_MINIMO && $valorCausa > 0) {
            $contratual = self::HONORARIO_MINIMO;
            $faixasAplicadas = [['faixa' => 'Honorário mínimo de referência', 'percentual' => 0.0, 'valor' => self::HONORARIO_MINIMO]];
        }

        $exito = $percentualExito > 0 ? round($valorCausa * ($percentualExito / 100), 2) : 0.0;
        $total = round($contratual + $exito, 2);

        $explicacao = sprintf(
            'Sobre uma causa de %s, o honorário contratual estimado (tabela progressiva) é de %s%s. %s',
            $this->formatarMoeda($valorCausa),
            $this->formatarMoeda($contratual),
            $percentualExito > 0
                ? sprintf(', mais %s de honorário de êxito (%s%% do valor da causa)', $this->formatarMoeda($exito), $this->formatarNumero($percentualExito))
                : '',
            'Estimativa de referência — consulte a tabela vigente da seccional OAB do escritório para a proposta final.',
        );

        return [
            'valor_causa' => $valorCausa,
            'honorario_contratual_estimado' => round($contratual, 2),
            'honorario_exito' => $exito,
            'percentual_exito' => $percentualExito,
            'honorario_total_estimado' => $total,
            'faixas_aplicadas' => $faixasAplicadas,
            'explicacao' => $explicacao,
        ];
    }

    /**
     * @return array{0: float, 1: list<array{faixa: string, percentual: float, valor: float}>}
     */
    private function calcularProgressivo(float $valorCausa): array
    {
        $total = 0.0;
        $faixasAplicadas = [];
        $anterior = 0.0;

        foreach (self::FAIXAS as $faixa) {
            if ($valorCausa <= $anterior) {
                break;
            }

            $limite = $faixa['ate'] ?? $valorCausa;
            $baseFaixa = min($valorCausa, $limite) - $anterior;

            if ($baseFaixa > 0) {
                $valorFaixa = round($baseFaixa * ($faixa['percentual'] / 100), 2);
                $total += $valorFaixa;
                $faixasAplicadas[] = [
                    'faixa' => $faixa['ate'] === null
                        ? sprintf('Acima de %s', $this->formatarMoeda($anterior))
                        : sprintf('%s a %s', $this->formatarMoeda($anterior), $this->formatarMoeda($faixa['ate'])),
                    'percentual' => $faixa['percentual'],
                    'valor' => $valorFaixa,
                ];
            }

            $anterior = $limite;
        }

        return [$total, $faixasAplicadas];
    }

    private function formatarMoeda(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private function formatarNumero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 1, ',', '.'), '0'), ',');
    }
}
