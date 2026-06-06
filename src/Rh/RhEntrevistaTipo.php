<?php

namespace App\Rh;

/** Modalidade da entrevista agendada. */
final class RhEntrevistaTipo
{
    public const ONLINE = 'ONLINE';
    public const PRESENCIAL = 'PRESENCIAL';

    /** @var list<string> */
    public const ALL = [self::ONLINE, self::PRESENCIAL];

    public static function label(?string $tipo): string
    {
        return match ($tipo) {
            self::PRESENCIAL => 'Presencial',
            default => 'Online',
        };
    }

    public static function isValid(?string $tipo): bool
    {
        return $tipo !== null && \in_array($tipo, self::ALL, true);
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
