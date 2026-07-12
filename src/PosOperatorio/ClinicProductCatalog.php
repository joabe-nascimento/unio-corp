<?php

namespace App\PosOperatorio;

/**
 * Produtos modulares da UNIO SAÚDE — pós-operatório, carteirinha e guia médico.
 */
final class ClinicProductCatalog
{
    public const POS_OPERATORIO = 'pos_operatorio';
    public const CARTEIRINHA = 'carteirinha_digital';
    public const COMPROVANTE = 'comprovante_procedimento';
    public const GUIA_MEDICO = 'guia_medico';

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            [
                'id' => self::POS_OPERATORIO,
                'label' => 'Pós-operatório',
                'short' => 'Pós-operatório',
                'desc' => 'Protocolos, questionários diários, alertas P1–P4 e acompanhamento pós-cirúrgico.',
                'icon' => 'fa-user-nurse',
                'tone' => 'sky',
                'maturity' => 'mvp',
                'route' => 'app_pos_operatorio_trabalho',
                'route_prefix' => 'app_pos_operatorio_trabalho',
                'route_label' => 'Abrir fila do dia',
                'route_hint' => 'Entrada do pós-operatório: abre a fila do dia',
                'marketing_route' => 'app_home',
                'marketing_anchor' => 'pos-operatorio',
                'default_enabled' => true,
                'capabilities' => [
                    'Pacientes e fichas clínicas',
                    'Protocolos por procedimento',
                    'Alertas com SLA',
                    'Portal do paciente integrado',
                ],
            ],
            [
                'id' => self::CARTEIRINHA,
                'label' => 'Carteirinha digital',
                'short' => 'Carteirinha',
                'desc' => 'Identidade clínica com foto, frente e verso, QR e validação na recepção.',
                'icon' => 'fa-id-card',
                'tone' => 'lavender',
                'maturity' => 'active',
                'route' => 'app_pos_operatorio_carteirinha',
                'route_prefix' => 'app_pos_operatorio_carteirinha',
                'route_label' => 'Emitir carteirinhas',
                'marketing_route' => 'app_carteirinha_digital',
                'marketing_params' => [],
                'default_enabled' => true,
                'capabilities' => [
                    'Foto do beneficiário',
                    'Modelos por plano',
                    'Código de verificação',
                    'QR de validação pública',
                    'Compartilhamento com o paciente',
                ],
            ],
            [
                'id' => self::COMPROVANTE,
                'label' => 'Comprovante de procedimento',
                'short' => 'Comprovante',
                'desc' => 'Documento do episódio cirúrgico com QR de validação pública na recepção.',
                'icon' => 'fa-file-medical',
                'tone' => 'sky',
                'maturity' => 'active',
                'route' => 'app_pos_operatorio_comprovante',
                'route_prefix' => 'app_pos_operatorio_comprovante',
                'route_label' => 'Emitir comprovantes',
                'marketing_route' => 'app_comprovante_procedimento',
                'marketing_params' => [],
                'default_enabled' => true,
                'capabilities' => [
                    'Resumo do procedimento e cirurgia',
                    'Código de verificação global',
                    'QR para validação na recepção',
                    'Compartilhamento com o paciente',
                ],
            ],
            [
                'id' => self::GUIA_MEDICO,
                'label' => 'Guia médico',
                'short' => 'Guia médico',
                'desc' => 'Orientações por fase da recuperação, marcos D+n e sinais de alerta no portal.',
                'icon' => 'fa-book-medical',
                'tone' => 'sage',
                'maturity' => 'active',
                'route' => 'app_pos_operatorio_guia_medico',
                'route_prefix' => 'app_pos_operatorio_guia_medico',
                'route_label' => 'Editar guias',
                'marketing_route' => 'app_guia_medico_beneficiario',
                'marketing_params' => [],
                'default_enabled' => true,
                'capabilities' => [
                    'Fases da recuperação',
                    'Orientações por procedimento',
                    'Sinais de alerta',
                    'Disponível no portal do paciente',
                ],
            ],
        ];
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }

        return null;
    }

    /** @return array<string, bool> */
    public static function defaultEnabledMap(): array
    {
        $map = [];
        foreach (self::all() as $product) {
            $map[(string) $product['id']] = (bool) ($product['default_enabled'] ?? true);
        }

        return $map;
    }
}
