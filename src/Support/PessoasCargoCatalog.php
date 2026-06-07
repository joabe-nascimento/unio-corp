<?php

namespace App\Support;

/** Catálogo de níveis e rótulos para cargos (Gestão de Pessoas). */
final class PessoasCargoCatalog
{
    /** @return list<array{value: string, label: string}> */
    public static function nivelOptions(bool $includeEmpty = true): array
    {
        $options = [
            ['value' => 'INICIANTE', 'label' => 'Iniciante — aprendizado estruturado'],
            ['value' => 'JUNIOR', 'label' => 'Júnior — execução com apoio'],
            ['value' => 'PLENO', 'label' => 'Pleno — autonomia operacional'],
            ['value' => 'SENIOR', 'label' => 'Sênior — referência técnica'],
            ['value' => 'ESPECIALISTA', 'label' => 'Especialista — profundidade em domínio'],
            ['value' => 'GESTAO', 'label' => 'Gestão — liderança de pessoas ou área'],
        ];

        if ($includeEmpty) {
            array_unshift($options, ['value' => '', 'label' => 'Selecione o nível…']);
        }

        return $options;
    }

    public static function nivelLabel(?string $nivel): string
    {
        if ($nivel === null || $nivel === '') {
            return '—';
        }

        foreach (self::nivelOptions(false) as $opt) {
            if ($opt['value'] === $nivel) {
                return explode(' — ', $opt['label'])[0];
            }
        }

        return ucfirst(strtolower($nivel));
    }

    public static function nivelVariant(?string $nivel): string
    {
        return match ($nivel) {
            'INICIANTE' => 'secondary',
            'JUNIOR' => 'info',
            'PLENO' => 'success',
            'SENIOR' => 'accent',
            'ESPECIALISTA' => 'warning',
            'GESTAO' => 'info',
            default => 'secondary',
        };
    }
}
