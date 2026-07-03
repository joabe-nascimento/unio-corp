<?php

namespace App\Service\PosOperatorio;

/**
 * Perguntas padrão do portal quando o protocolo não define campos customizados.
 */
final class PosOperatorioProtocoloDefaults
{
    /** @return list<array<string, mixed>> */
    public static function perguntas(): array
    {
        return [
            ['id' => 'dor', 'tipo' => 'escala', 'label' => 'Nível de dor (0–10)', 'min' => 0, 'max' => 10, 'default' => 3],
            ['id' => 'febre', 'tipo' => 'numero', 'label' => 'Temperatura (°C)', 'step' => 0.1, 'placeholder' => '36.5'],
            [
                'id' => 'nausea',
                'tipo' => 'select',
                'label' => 'Náusea ou vômito?',
                'opcoes' => [
                    ['value' => 'nao', 'label' => 'Não'],
                    ['value' => 'leve', 'label' => 'Leve'],
                    ['value' => 'persistente', 'label' => 'Persistente'],
                ],
            ],
            [
                'id' => 'sangramento',
                'tipo' => 'select',
                'label' => 'Curativo / sangramento',
                'opcoes' => [
                    ['value' => 'normal', 'label' => 'Normal'],
                    ['value' => 'leve', 'label' => 'Sangramento leve'],
                    ['value' => 'intenso', 'label' => 'Sangramento intenso'],
                ],
            ],
            ['id' => 'observacao', 'tipo' => 'texto', 'label' => 'Observações', 'optional' => true],
        ];
    }

    /** @return array<string, mixed> */
    public static function regrasAlerta(): array
    {
        return ['dor_p1_min' => 8, 'febre_p2_min' => 38.5];
    }

    /** @return list<array<string, mixed>> */
    public static function checklistBasico(): array
    {
        return [
            ['dia' => 1, 'item' => 'Repouso relativo e hidratação'],
            ['dia' => 3, 'item' => 'Verificar curativo / ponto cirúrgico'],
            ['dia' => 7, 'item' => 'Retorno ambulatorial se agendado'],
        ];
    }
}
