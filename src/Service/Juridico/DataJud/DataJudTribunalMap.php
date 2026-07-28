<?php

namespace App\Service\Juridico\DataJud;

/**
 * Decodifica o número de processo no padrão CNJ (Resolução CNJ 65/2008) para descobrir
 * o alias do tribunal na API Pública do DataJud (CNJ) — a base nacional que agrega os
 * dados oficiais vindos de PJe, e-SAJ, Projudi, EPROC e demais sistemas processuais.
 *
 * Formato do número: NNNNNNN-DD.AAAA.J.TR.OOOO
 *   J  = segmento do judiciário (1 dígito)
 *   TR = tribunal dentro do segmento (2 dígitos)
 */
final class DataJudTribunalMap
{
    /** Segmento 8 — Justiça Estadual (TJs), na ordem oficial CNJ. */
    private const TJ = [
        '01' => 'tjac', '02' => 'tjal', '03' => 'tjap', '04' => 'tjam', '05' => 'tjba',
        '06' => 'tjce', '07' => 'tjdft', '08' => 'tjes', '09' => 'tjgo', '10' => 'tjma',
        '11' => 'tjmt', '12' => 'tjms', '13' => 'tjmg', '14' => 'tjpa', '15' => 'tjpb',
        '16' => 'tjpr', '17' => 'tjpe', '18' => 'tjpi', '19' => 'tjrj', '20' => 'tjrn',
        '21' => 'tjrs', '22' => 'tjro', '23' => 'tjrr', '24' => 'tjsc', '25' => 'tjsp',
        '26' => 'tjse', '27' => 'tjto',
    ];

    /** Segmento 4 — Justiça Federal (TRFs). */
    private const TRF = [
        '01' => 'trf1', '02' => 'trf2', '03' => 'trf3', '04' => 'trf4', '05' => 'trf5', '06' => 'trf6',
    ];

    /** Segmento 5 — Justiça do Trabalho (TST + TRTs). */
    private const TRT = [
        '00' => 'tst',
        '01' => 'trt1', '02' => 'trt2', '03' => 'trt3', '04' => 'trt4', '05' => 'trt5',
        '06' => 'trt6', '07' => 'trt7', '08' => 'trt8', '09' => 'trt9', '10' => 'trt10',
        '11' => 'trt11', '12' => 'trt12', '13' => 'trt13', '14' => 'trt14', '15' => 'trt15',
        '16' => 'trt16', '17' => 'trt17', '18' => 'trt18', '19' => 'trt19', '20' => 'trt20',
        '21' => 'trt21', '22' => 'trt22', '23' => 'trt23', '24' => 'trt24',
    ];

    /** Segmento 6 — Justiça Eleitoral (TSE + TREs, mesma ordem de UF do segmento 8). */
    private const TRE = [
        '00' => 'tse', '01' => 'treac', '02' => 'treal', '03' => 'treap', '04' => 'tream',
        '05' => 'treba', '06' => 'trece', '07' => 'tredf', '08' => 'trees', '09' => 'trego',
        '10' => 'trema', '11' => 'tremt', '12' => 'trems', '13' => 'tremg', '14' => 'trepa',
        '15' => 'trepb', '16' => 'trepr', '17' => 'trepe', '18' => 'trepi', '19' => 'trerj',
        '20' => 'trern', '21' => 'trers', '22' => 'trero', '23' => 'trerr', '24' => 'tresc',
        '25' => 'tresp', '26' => 'trese', '27' => 'treto',
    ];

    /** Segmento 9 — Justiça Militar Estadual. */
    private const TJM = ['01' => 'tjmsp', '02' => 'tjmmg', '03' => 'tjmrs'];

    /** @return array{numero: string, alias: string, tribunal: string}|null */
    public static function resolver(string $numeroProcesso): ?array
    {
        $digits = preg_replace('/\D/', '', $numeroProcesso) ?? '';
        if (\strlen($digits) !== 20) {
            return null;
        }

        $segmento = substr($digits, 13, 1);
        $tribunal = substr($digits, 14, 2);

        $alias = match ($segmento) {
            '1' => 'stf',
            '3' => 'stj',
            '4' => self::TRF[$tribunal] ?? null,
            '5' => self::TRT[$tribunal] ?? null,
            '6' => self::TRE[$tribunal] ?? null,
            '7' => 'stm',
            '8' => self::TJ[$tribunal] ?? null,
            '9' => self::TJM[$tribunal] ?? null,
            default => null,
        };

        if ($alias === null) {
            return null;
        }

        return [
            'numero' => self::formatar($digits),
            'alias' => $alias,
            'tribunal' => strtoupper($alias),
        ];
    }

    private static function formatar(string $digits): string
    {
        return sprintf(
            '%s-%s.%s.%s.%s.%s',
            substr($digits, 0, 7),
            substr($digits, 7, 2),
            substr($digits, 9, 4),
            substr($digits, 13, 1),
            substr($digits, 14, 2),
            substr($digits, 16, 4),
        );
    }
}
