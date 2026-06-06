<?php

namespace App\Rh;

/** Critérios padrão de scorecard por etapa do funil. */
final class RhScorecardCriteria
{
    /** @return list<array{id: string, label: string}> */
    public static function forEtapa(string $etapa): array
    {
        return match ($etapa) {
            RhCandidatoEtapa::ENTREVISTA => [
                ['id' => 'comunicacao', 'label' => 'Comunicação'],
                ['id' => 'tecnico', 'label' => 'Conhecimento técnico'],
                ['id' => 'cultura', 'label' => 'Fit cultural'],
                ['id' => 'motivacao', 'label' => 'Motivação'],
            ],
            RhCandidatoEtapa::PROPOSTA => [
                ['id' => 'experiencia', 'label' => 'Experiência relevante'],
                ['id' => 'salario', 'label' => 'Expectativa salarial'],
                ['id' => 'disponibilidade', 'label' => 'Disponibilidade'],
            ],
            RhCandidatoEtapa::CONTRATADO => [
                ['id' => 'referencias', 'label' => 'Referências'],
                ['id' => 'documentacao', 'label' => 'Documentação'],
            ],
            default => [
                ['id' => 'perfil', 'label' => 'Perfil geral'],
                ['id' => 'requisitos', 'label' => 'Atende requisitos'],
            ],
        };
    }

    /** @return list<string> */
    public static function etapasComScorecard(): array
    {
        return [
            RhCandidatoEtapa::TRIAGEM,
            RhCandidatoEtapa::ENTREVISTA,
            RhCandidatoEtapa::PROPOSTA,
            RhCandidatoEtapa::CONTRATADO,
        ];
    }
}
