<?php

namespace App\Rh;

/** Rótulos de exibição para processos de admissão/demissão. */
final class RhProcessDisplay
{
    /** @var list<string> */
    private const GENERIC_HUB_NAMES = [
        'hub operações',
        'hub operacoes',
        'hub de operações',
        'hub de operacoes',
        'núcleo pós-operatório',
        'nucleo pos-operatorio',
        'núcleo pos-operatório',
        'núcleo de operações',
        'nucleo de operacoes',
        'hub recrutamento',
        'núcleo de recrutamento',
        'nucleo de recrutamento',
        'hub rh',
        'recursos humanos',
        'gestão de pessoas',
        'gestao de pessoas',
    ];

    public static function colaboradorNome(string $nome, ?string $email = null, ?string $empresaNome = null): string
    {
        $trimmed = trim($nome);
        if ($trimmed === '') {
            return self::nomeFromEmail($email) ?? 'Colaborador';
        }

        if (self::isGenericHubName($trimmed) || self::matchesEmpresaNome($trimmed, $empresaNome)) {
            return self::nomeFromEmail($email) ?? 'Colaborador';
        }

        return $trimmed;
    }

    public static function isGenericHubName(string $nome): bool
    {
        $normalized = self::normalize($nome);
        if (\in_array($normalized, self::GENERIC_HUB_NAMES, true)) {
            return true;
        }

        return preg_match('/^(hub|núcleo|nucleo)(\s+de)?\s+/u', $normalized) === 1;
    }

    public static function nomeFromEmail(?string $email): ?string
    {
        if ($email === null || $email === '' || !str_contains($email, '@')) {
            return null;
        }

        $local = explode('@', $email, 2)[0];
        $local = trim(str_replace(['.', '-', '_'], ' ', $local));
        if ($local === '') {
            return null;
        }

        return mb_convert_case($local, \MB_CASE_TITLE, 'UTF-8');
    }

    private static function matchesEmpresaNome(string $nome, ?string $empresaNome): bool
    {
        if ($empresaNome === null || trim($empresaNome) === '') {
            return false;
        }

        return self::normalize($nome) === self::normalize($empresaNome);
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
