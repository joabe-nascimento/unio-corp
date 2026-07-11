<?php

namespace App\Dev;

/**
 * E-mails dos usuários de desenvolvimento/demo (nomes reais, não roles genéricos).
 */
final class DevSeedEmails
{
    public const JOABE = 'joabe.nascimento@unio.dev';
    public const RENATA = 'renata.oliveira@unio.dev';
    public const RICARDO = 'ricardo.costa@unio.dev';
    public const ANA_PAULA = 'ana.ribeiro@unio.dev';
    public const FELIPE = 'felipe.martins@unio.dev';
    public const LUCAS = 'lucas.santos@unio.dev';
    public const MARCELA = 'marcela.ferreira@nexus.dev';
    public const PATRICIA = 'patricia.almeida@edu360.dev';

    /** @var array<string, string> e-mail atual => legado (migração idempotente do seed) */
    public const LEGACY = [
        self::JOABE => 'tenant@unio.dev',
        self::RENATA => 'gestor@unio.dev',
        self::RICARDO => 'gestor.eq@unio.dev',
        self::ANA_PAULA => 'supervisor@unio.dev',
        self::FELIPE => 'sup.eq@unio.dev',
        self::LUCAS => 'membro@unio.dev',
        self::MARCELA => 'gestor@nexus.dev',
        self::PATRICIA => 'gestor@edu360.dev',
    ];

    /** @return list<string> */
    public static function primaryAccounts(): array
    {
        return [
            self::JOABE,
            self::RENATA,
            self::LUCAS,
            self::ANA_PAULA,
        ];
    }

    public static function legacyEmailFor(string $email): ?string
    {
        return self::LEGACY[$email] ?? null;
    }

    /** Identificador estável de membro (grants / matriz) a partir do e-mail. */
    public static function memberSlotId(string $email): string
    {
        foreach (self::LEGACY as $canonical => $legacy) {
            if ($email === $canonical || $email === $legacy) {
                $local = explode('@', $canonical)[0] ?? $canonical;

                return str_replace('.', '-', $local);
            }
        }

        $local = explode('@', $email)[0] ?? $email;

        return str_replace('.', '-', $local);
    }
}
