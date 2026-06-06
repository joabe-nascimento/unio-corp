<?php

namespace App\Rh;

/** Origem / canal de captação do candidato. */
final class RhCandidatoOrigem
{
    public const MANUAL = 'MANUAL';
    public const INDICACAO = 'INDICACAO';
    public const LINKEDIN = 'LINKEDIN';
    public const SITE = 'SITE';
    public const INDEED = 'INDEED';
    public const EVENTO = 'EVENTO';
    public const BANCO_TALENTOS = 'BANCO_TALENTOS';

    /** @var list<string> */
    public const ALL = [
        self::MANUAL,
        self::INDICACAO,
        self::LINKEDIN,
        self::SITE,
        self::INDEED,
        self::EVENTO,
        self::BANCO_TALENTOS,
    ];

    public static function label(string $origem): string
    {
        return match ($origem) {
            self::INDICACAO => 'Indicação',
            self::LINKEDIN => 'LinkedIn',
            self::SITE => 'Site / carreiras',
            self::INDEED => 'Indeed / job boards',
            self::EVENTO => 'Evento / feira',
            self::BANCO_TALENTOS => 'Banco de talentos',
            default => 'Cadastro manual',
        };
    }

    public static function isValid(string $origem): bool
    {
        return \in_array($origem, self::ALL, true);
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        $options = [];
        foreach (self::ALL as $id) {
            $options[] = ['value' => $id, 'label' => self::label($id)];
        }

        return $options;
    }
}
