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
        return [
            'dor_p1_min' => 8,
            'dor_p2_min' => 6,
            'febre_p2_min' => 38.5,
            'sangramento_p1' => 'intenso',
        ];
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

    /** @return list<array<string, mixed>> */
    public static function checklistPreOp(): array
    {
        return [
            ['dia' => -7, 'item' => 'Exames pré-operatórios e avaliação clínica'],
            ['dia' => -3, 'item' => 'Revisar jejum, medicações e orientações'],
            ['dia' => -1, 'item' => 'Confirmar presença e checklist final'],
            ['dia' => 0, 'item' => 'Dia da cirurgia — handoff pré → pós'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function checklistPreOpOrtognatica(): array
    {
        return [
            ['dia' => -14, 'item' => 'Exames laboratoriais e avaliação anestésica'],
            ['dia' => -7, 'item' => 'Confirmar jejum prolongado e higiene oral'],
            ['dia' => -3, 'item' => 'Revisar medicações e preparo de dieta pastosa'],
            ['dia' => -1, 'item' => 'Confirmar presença, acompanhante e checklist final'],
            ['dia' => 0, 'item' => 'Dia da cirurgia — handoff pré → pós'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function checklistPreOpRinoplastia(): array
    {
        return [
            ['dia' => -7, 'item' => 'Fotos pré, exames e suspensão de anticoagulantes se válido'],
            ['dia' => -3, 'item' => 'Orientações de edema, splint e higiene nasal'],
            ['dia' => -1, 'item' => 'Confirmar presença e checklist final'],
            ['dia' => 0, 'item' => 'Dia da cirurgia — handoff pré → pós'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function checklistPreOpCesarea(): array
    {
        return [
            ['dia' => -7, 'item' => 'Exames pré-natal finais e avaliação obstétrica'],
            ['dia' => -3, 'item' => 'Revisar jejum, bolsa e plano de analgesía'],
            ['dia' => -1, 'item' => 'Confirmar horário, acompanhante e checklist final'],
            ['dia' => 0, 'item' => 'Dia da cirurgia — handoff pré → pós'],
        ];
    }

    /** Check-in curto no portal antes da cirurgia. */
    /** @return list<array<string, mixed>> */
    public static function perguntasPreOp(): array
    {
        return [
            [
                'id' => 'preparado',
                'tipo' => 'select',
                'label' => 'Você se sente preparado(a) para a cirurgia?',
                'opcoes' => [
                    ['value' => 'sim', 'label' => 'Sim'],
                    ['value' => 'duvidas', 'label' => 'Tenho dúvidas'],
                    ['value' => 'nao', 'label' => 'Ainda não'],
                ],
            ],
            [
                'id' => 'jejum',
                'tipo' => 'select',
                'label' => 'Orientações de jejum / alimentação',
                'opcoes' => [
                    ['value' => 'ok', 'label' => 'Já entendi e vou seguir'],
                    ['value' => 'duvida', 'label' => 'Tenho dúvida'],
                    ['value' => 'nao_recebi', 'label' => 'Ainda não recebi orientação'],
                ],
            ],
            [
                'id' => 'medicamentos',
                'tipo' => 'select',
                'label' => 'Medicações em uso',
                'opcoes' => [
                    ['value' => 'conforme', 'label' => 'Seguirei conforme orientação'],
                    ['value' => 'duvida', 'label' => 'Preciso esclarecer com a equipe'],
                    ['value' => 'nenhuma', 'label' => 'Não uso medicações'],
                ],
            ],
            ['id' => 'observacao', 'tipo' => 'texto', 'label' => 'Dúvidas ou observações', 'optional' => true],
        ];
    }
}
