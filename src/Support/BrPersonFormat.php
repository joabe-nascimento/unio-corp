<?php

namespace App\Support;

final class BrPersonFormat
{
    public static function digitsOnly(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }

    public static function formatCpf(?string $value): string
    {
        $d = self::digitsOnly($value);
        if ($d === null || strlen($d) !== 11) {
            return (string) ($value ?? '');
        }

        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }

    public static function formatPhone(?string $value): string
    {
        $d = self::digitsOnly($value);
        if ($d === null) {
            return '';
        }
        if (strlen($d) === 11) {
            return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7, 4));
        }
        if (strlen($d) === 10) {
            return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6, 4));
        }

        return (string) $value;
    }

    public static function formatCep(?string $value): string
    {
        $d = self::digitsOnly($value);
        if ($d === null || strlen($d) !== 8) {
            return (string) ($value ?? '');
        }

        return substr($d, 0, 5) . '-' . substr($d, 5, 3);
    }

    public static function formatMoney(?string $decimal): string
    {
        if ($decimal === null || $decimal === '') {
            return '';
        }

        return number_format((float) $decimal, 2, ',', '.');
    }

    public static function parseMoney(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $raw = trim(str_replace(['R$', ' '], '', $value));
        if ($raw === '') {
            return null;
        }
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
        if (!is_numeric($raw)) {
            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }

    public static function isValidCpf(?string $value): bool
    {
        $cpf = self::digitsOnly($value);
        if ($cpf === null || strlen($cpf) !== 11) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; ++$t) {
            $sum = 0;
            for ($i = 0; $i < $t; ++$i) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
