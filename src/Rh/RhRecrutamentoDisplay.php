<?php

namespace App\Rh;

use App\Entity\RhCandidato;

/** Rótulos de exibição do módulo de recrutamento. */
final class RhRecrutamentoDisplay
{
    public static function formatNome(string $nome): string
    {
        return str_replace(
            ['Hub Operações', 'Hub de ', 'Hub '],
            ['Núcleo de Operações', 'Núcleo de ', 'Núcleo '],
            $nome,
        );
    }

    public static function entrevistaTitulo(RhCandidato $candidato): string
    {
        return sprintf(
            'Entrevista — %s — %s',
            self::formatNome($candidato->getNome()),
            $candidato->getVaga()->getTitulo(),
        );
    }
}
