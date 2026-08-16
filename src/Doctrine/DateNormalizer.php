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

    public static function fromFormDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $fmt) {
            $date = \DateTimeImmutable::createFromFormat($fmt, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        return self::fromFormDate($value);
    }
}
