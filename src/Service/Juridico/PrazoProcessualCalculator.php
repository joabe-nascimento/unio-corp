<?php

namespace App\Service\Juridico;

/**
 * Calculadora de prazos processuais (padrão CPC/CPC-PT brasileiro).
 *
 * Regras aplicadas:
 * - Art. 224, CPC: exclui o dia do começo, inclui o dia do vencimento.
 * - Art. 219, CPC: prazos em dias contam-se apenas em dias úteis (salvo prazo "corrido").
 * - Art. 229, CPC: prazo em dobro para litisconsortes com procuradores distintos.
 * - Art. 220, CPC + Res. CNJ: suspensão entre 20/dez e 20/jan (recesso forense).
 * - Se o vencimento cair em dia não útil, prorroga-se para o próximo dia útil.
 *
 * Feriados nacionais fixos + móveis (baseados na Páscoa) são calculados automaticamente,
 * sem depender de tabela externa — cobre qualquer ano.
 */
final class PrazoProcessualCalculator
{
    public const TIPO_UTIL = 'util';
    public const TIPO_CORRIDO = 'corrido';

    /**
     * @return array{
     *     data_final: \DateTimeImmutable,
     *     dias_efetivos: int,
     *     tipo: string,
     *     dobro: bool,
     *     recesso_aplicado: bool,
     *     feriados_no_periodo: list<array{data: string, nome: string}>,
     *     explicacao: string
     * }
     */
    public function calcular(
        \DateTimeImmutable $dataBase,
        int $dias,
        string $tipo = self::TIPO_UTIL,
        bool $dobro = false,
        bool $considerarRecesso = true,
    ): array {
        $tipo = \in_array($tipo, [self::TIPO_UTIL, self::TIPO_CORRIDO], true) ? $tipo : self::TIPO_UTIL;
        $diasContar = $dobro ? $dias * 2 : $dias;

        $feriadosEncontrados = [];
        $cursor = $dataBase;
        $contados = 0;

        while ($contados < $diasContar) {
            $cursor = $cursor->modify('+1 day');
            $motivoNaoUtil = $this->motivoNaoUtil($cursor, $considerarRecesso);

            if ($tipo === self::TIPO_CORRIDO) {
                $contados++;
                continue;
            }

            if ($motivoNaoUtil !== null) {
                if ($motivoNaoUtil !== 'fim_de_semana') {
                    $feriadosEncontrados[$cursor->format('Y-m-d')] = ['data' => $cursor->format('d/m/Y'), 'nome' => $motivoNaoUtil];
                }
                continue;
            }

            $contados++;
        }

        // Se o vencimento (prazo corrido) cair em dia não útil, prorroga (Art. 224 §1º).
        if ($tipo === self::TIPO_CORRIDO) {
            while ($this->motivoNaoUtil($cursor, $considerarRecesso) !== null) {
                $motivo = $this->motivoNaoUtil($cursor, $considerarRecesso);
                if ($motivo !== null && $motivo !== 'fim_de_semana') {
                    $feriadosEncontrados[$cursor->format('Y-m-d')] = ['data' => $cursor->format('d/m/Y'), 'nome' => $motivo];
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        $explicacao = $this->montarExplicacao($dataBase, $cursor, $dias, $tipo, $dobro, $considerarRecesso);

        return [
            'data_final' => $cursor,
            'dias_efetivos' => $diasContar,
            'tipo' => $tipo,
            'dobro' => $dobro,
            'recesso_aplicado' => $considerarRecesso,
            'feriados_no_periodo' => array_values($feriadosEncontrados),
            'explicacao' => $explicacao,
        ];
    }

    /**
     * Prazos estatutários comuns (dias úteis), para sugestão automática quando o
     * usuário menciona o tipo de peça sem informar o número de dias.
     *
     * @return array<string, int>
     */
    public static function prazosComuns(): array
    {
        return [
            'contestação' => 15,
            'contestacao' => 15,
            'réplica' => 15,
            'replica' => 15,
            'apelação' => 15,
            'apelacao' => 15,
            'contrarrazões' => 15,
            'contrarrazoes' => 15,
            'agravo de instrumento' => 15,
            'agravo interno' => 15,
            'embargos de declaração' => 5,
            'embargos de declaracao' => 5,
            'embargos à execução' => 15,
            'embargos a execucao' => 15,
            'recurso especial' => 15,
            'recurso extraordinário' => 15,
            'recurso extraordinario' => 15,
            'impugnação ao cumprimento de sentença' => 15,
            'impugnacao ao cumprimento de sentenca' => 15,
            'manifestação' => 5,
            'manifestacao' => 5,
            'mandado de segurança' => 120,
            'mandado de seguranca' => 120,
        ];
    }

    private function motivoNaoUtil(\DateTimeImmutable $data, bool $considerarRecesso): ?string
    {
        $diaSemana = (int) $data->format('N');
        if ($diaSemana >= 6) {
            return 'fim_de_semana';
        }

        if ($considerarRecesso && $this->estaNoRecessoForense($data)) {
            return 'Recesso forense (Art. 220, CPC)';
        }

        $feriado = $this->feriadosNacionais((int) $data->format('Y'))[$data->format('Y-m-d')] ?? null;

        return $feriado;
    }

    /** Recesso forense: 20/dez a 20/jan (Art. 220, CPC — suspensão de prazos). */
    private function estaNoRecessoForense(\DateTimeImmutable $data): bool
    {
        $mes = (int) $data->format('n');
        $dia = (int) $data->format('j');

        return ($mes === 12 && $dia >= 20) || ($mes === 1 && $dia <= 20);
    }

    /** @return array<string, string> Y-m-d => nome do feriado */
    private function feriadosNacionais(int $ano): array
    {
        $pascoa = $this->calcularPascoa($ano);
        $carnaval = $pascoa->modify('-47 days');
        $sextaSanta = $pascoa->modify('-2 days');
        $corpusChristi = $pascoa->modify('+60 days');

        $lista = [
            $ano . '-01-01' => 'Confraternização Universal',
            $carnaval->format('Y-m-d') => 'Carnaval',
            $sextaSanta->format('Y-m-d') => 'Sexta-feira Santa',
            $corpusChristi->format('Y-m-d') => 'Corpus Christi',
            $ano . '-04-21' => 'Tiradentes',
            $ano . '-05-01' => 'Dia do Trabalho',
            $ano . '-09-07' => 'Independência do Brasil',
            $ano . '-10-12' => 'Nossa Senhora Aparecida',
            $ano . '-11-02' => 'Finados',
            $ano . '-11-15' => 'Proclamação da República',
            $ano . '-11-20' => 'Dia Nacional de Zumbi e da Consciência Negra',
            $ano . '-12-25' => 'Natal',
        ];

        return $lista;
    }

    /** Algoritmo de Gauss/Meeus para a Páscoa (calendário gregoriano). */
    private function calcularPascoa(int $ano): \DateTimeImmutable
    {
        $a = $ano % 19;
        $b = intdiv($ano, 100);
        $c = $ano % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano, $mes, $dia));
    }

    private function montarExplicacao(
        \DateTimeImmutable $base,
        \DateTimeImmutable $final,
        int $dias,
        string $tipo,
        bool $dobro,
        bool $recesso,
    ): string {
        $partes = [];
        $partes[] = sprintf(
            'Contando a partir de %s (excluindo o dia do começo, conforme Art. 224 do CPC)',
            $base->format('d/m/Y'),
        );
        $partes[] = sprintf('%d dia(s) %s', $dias, $tipo === self::TIPO_UTIL ? 'úteis' : 'corridos');
        if ($dobro) {
            $partes[] = 'em dobro (Art. 229, CPC — litisconsortes com procuradores distintos)';
        }
        if ($recesso) {
            $partes[] = 'considerando o recesso forense (20/dez a 20/jan)';
        }
        $partes[] = sprintf('o prazo vence em %s (%s)', $final->format('d/m/Y'), $this->diaSemanaPtBr($final));

        return implode(', ', $partes) . '.';
    }

    private function diaSemanaPtBr(\DateTimeImmutable $data): string
    {
        $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

        return $dias[(int) $data->format('w')];
    }
}
