<?php

namespace App\Http;

/**
 * Lê inteiros de request/query sem disparar BadRequestException do Symfony
 * quando o campo vem vazio (ex.: select "Todos" → medico_id=).
 */
final class RequestInts
{
    public static function optional(mixed $raw): ?int
    {
        if ($raw === null || $raw === false) {
            return null;
        }
        if (\is_int($raw)) {
            return $raw;
        }
        if (\is_float($raw)) {
            return (int) $raw;
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

        if (!preg_match('/^-?\d+$/', $s)) {
            return null;
        }

        return (int) $s;
    }

    /** Inteiro positivo ou null (0 / vazio / inválido → null). */
    public static function positiveOrNull(mixed $raw): ?int
    {
        $v = self::optional($raw);

        return $v !== null && $v > 0 ? $v : null;
    }

    public static function withDefault(mixed $raw, int $default): int
    {
        $v = self::optional($raw);

        return $v ?? $default;
    }
}
