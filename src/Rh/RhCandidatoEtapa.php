<?php

namespace App\Rh;

/** Etapas do pipeline de seleção (ATS). */
final class RhCandidatoEtapa
{
    public const TRIAGEM = 'TRIAGEM';
    public const ENTREVISTA = 'ENTREVISTA';
    public const PROPOSTA = 'PROPOSTA';
    public const CONTRATADO = 'CONTRATADO';
    public const REPROVADO = 'REPROVADO';

    /** @var list<string> */
    public const PIPELINE_ORDER = [
        self::TRIAGEM,
        self::ENTREVISTA,
        self::PROPOSTA,
        self::CONTRATADO,
    ];

    /** @var list<string> */
    public const BOARD_ORDER = [
        self::TRIAGEM,
        self::ENTREVISTA,
        self::PROPOSTA,
        self::CONTRATADO,
        self::REPROVADO,
    ];

    public static function label(string $etapa): string
    {
        return match ($etapa) {
            self::ENTREVISTA => 'Entrevista',
            self::PROPOSTA => 'Proposta',
            self::CONTRATADO => 'Contratado',
            self::REPROVADO => 'Reprovado',
            default => 'Triagem',
        };
    }

    public static function badgeVariant(string $etapa): string
    {
        return match ($etapa) {
            self::REPROVADO => 'danger',
            self::CONTRATADO => 'success',
            default => 'info',
        };
    }

    public static function icon(string $etapa): string
    {
        return match ($etapa) {
            self::ENTREVISTA => 'fa-comments',
            self::PROPOSTA => 'fa-file-signature',
            self::CONTRATADO => 'fa-user-check',
            self::REPROVADO => 'fa-user-xmark',
            default => 'fa-inbox',
        };
    }

    public static function isValid(string $etapa): bool
    {
        return \in_array($etapa, self::BOARD_ORDER, true);
    }

    public static function next(string $etapa): ?string
    {
        $idx = array_search($etapa, self::PIPELINE_ORDER, true);
        if ($idx === false || $idx >= \count(self::PIPELINE_ORDER) - 1) {
            return null;
        }

        return self::PIPELINE_ORDER[$idx + 1];
    }

    public static function prev(string $etapa): ?string
    {
        $idx = array_search($etapa, self::PIPELINE_ORDER, true);
        if ($idx === false || $idx <= 0) {
            return null;
        }

        return self::PIPELINE_ORDER[$idx - 1];
    }

    /** @return list<array{id: string, label: string, icon: string}> */
    public static function boardStages(): array
    {
        $stages = [];
        foreach (self::BOARD_ORDER as $id) {
            $stages[] = ['id' => $id, 'label' => self::label($id), 'icon' => self::icon($id)];
        }

        return $stages;
    }
}
