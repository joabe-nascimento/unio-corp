<?php

namespace App\Rh;

/** Modelo de contratação da vaga. */
final class RhVagaTipoContrato
{
    public const CLT = 'CLT';
    public const PJ = 'PJ';
    public const ESTAGIO = 'ESTAGIO';
    public const TEMPORARIO = 'TEMPORARIO';
    public const APRENDIZ = 'APRENDIZ';

    /** @var list<string> */
    public const ALL = [
        self::CLT,
        self::PJ,
        self::ESTAGIO,
        self::TEMPORARIO,
        self::APRENDIZ,
    ];

    public static function label(string $tipo): string
    {
        return match ($tipo) {
            self::PJ => 'PJ / freelancer',
            self::ESTAGIO => 'Estágio',
            self::TEMPORARIO => 'Temporário',
            self::APRENDIZ => 'Aprendiz',
            default => 'CLT',
        };
    }

    public static function isValid(?string $tipo): bool
    {
        return $tipo === null || $tipo === '' || \in_array($tipo, self::ALL, true);
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        $options = [['value' => '', 'label' => 'Não informado']];
        foreach (self::ALL as $id) {
            $options[] = ['value' => $id, 'label' => self::label($id)];
        }

        return $options;
    }
}
