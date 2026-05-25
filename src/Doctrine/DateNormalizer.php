<?php

namespace App\Doctrine;

/**
 * Normaliza valores de data para colunas Doctrine {@see date_immutable}.
 */
final class DateNormalizer
{
    public static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today');
    }

    public static function immutable(?\DateTimeInterface $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeImmutable
            ? $value
            : \DateTimeImmutable::createFromInterface($value);
    }

    public static function immutableOrToday(?\DateTimeInterface $value): \DateTimeImmutable
    {
        return self::immutable($value) ?? self::today();
    }

    public static function fromFormDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date ?: null;
    }
}
