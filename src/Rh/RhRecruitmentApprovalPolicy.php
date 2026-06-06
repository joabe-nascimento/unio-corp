<?php

namespace App\Rh;

/** Etapas que exigem aprovação de gestor antes de avançar. */
final class RhRecruitmentApprovalPolicy
{
    /** @return list<string> */
    public static function etapasQueExigemAprovacao(): array
    {
        return [
            RhCandidatoEtapa::PROPOSTA,
            RhCandidatoEtapa::CONTRATADO,
        ];
    }

    public static function exigeAprovacao(string $etapa): bool
    {
        return \in_array($etapa, self::etapasQueExigemAprovacao(), true);
    }
}
