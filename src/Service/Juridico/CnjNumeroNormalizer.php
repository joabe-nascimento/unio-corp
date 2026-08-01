<?php

namespace App\Service\Juridico;

/**
 * Normalização de números CNJ para matching entre DJEN, DataJud e cadastro interno.
 */
final class CnjNumeroNormalizer
{
    public static function apenasDigitos(?string $numero): ?string
    {
        if ($numero === null || trim($numero) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $numero);

        return $digits !== '' ? $digits : null;
    }

    public static function formatarMascara(?string $numero): ?string
    {
        $digits = self::apenasDigitos($numero);
        if ($digits === null || strlen($digits) !== 20) {
            return $numero !== null ? trim($numero) : null;
        }

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

    public static function equivalentes(?string $a, ?string $b): bool
    {
        $da = self::apenasDigitos($a);
        $db = self::apenasDigitos($b);

        return $da !== null && $db !== null && $da === $db;
    }
}
