<?php

namespace App\Clinic;

/**
 * Perfis operacionais da Unio Saúde — únicos perfis de acesso clínico.
 */
final class ClinicStaffRole
{
    public const RECEPCAO = 'RECEPCAO';
    public const ENFERMAGEM = 'ENFERMAGEM';
    public const MEDICO = 'MEDICO';
    public const COORDENACAO = 'COORDENACAO';

    public const SCOPE = 'hub_pos_operatorio';

    /** @var list<string> */
    public const ALL = [
        self::RECEPCAO,
        self::ENFERMAGEM,
        self::MEDICO,
        self::COORDENACAO,
    ];

    /**
     * Produtos liberados por perfil (rotas / grants).
     *
     * @var array<string, list<string>>
     */
    public const PRODUCTS_BY_ROLE = [
        self::RECEPCAO => [
            'pacientes',
            'operacao',
        ],
        self::ENFERMAGEM => [
            'questionarios',
            'painel',
            'portal_paciente',
            'pacientes', // ficha na triagem (links dos questionários)
        ],
        self::MEDICO => [
            'alertas',
            'pacientes',
            'protocolos',
            'painel',
        ],
        self::COORDENACAO => [
            'relatorios',
            'configuracoes',
            'pacientes',
        ],
    ];

    /**
     * Itens da sidebar por perfil (mais restrito que o produto).
     *
     * @var array<string, list<string>>
     */
    public const FEATURES_BY_ROLE = [
        self::RECEPCAO => [
            'pacientes',
            'retornos',
            'agenda',
            'atendimento',
            'contas',
            'convenios',
            'guias',
            'lotes',
            'carteirinha',
            'comprovante',
            'recepcao',
        ],
        self::ENFERMAGEM => [
            'trabalho',
            'pacientes',
            'questionarios',
            'lembretes',
            'portal',
            'painel_dia',
        ],
        self::MEDICO => [
            'trabalho',
            'pacientes',
            'protocolos',
            'biblioteca',
            'retornos',
            'agenda',
            'atendimento',
            'alertas',
            'sala_critica',
            'plantao',
            'contrato_cuidado',
            'guia_medico',
            'painel_dia',
        ],
        self::COORDENACAO => [
            'qualidade',
            'relatorios',
            'integracoes',
            'compliance',
            'config',
            'produtos',
            'comercial',
            'crm',
            'pacientes',
        ],
    ];

    /**
     * Home operacional após login (além do Pulso shell).
     *
     * @var array<string, string>
     */
    public const HOME_ROUTE_BY_ROLE = [
        self::RECEPCAO => 'app_pos_operatorio_pacientes',
        self::ENFERMAGEM => 'app_pos_operatorio_questionarios',
        self::MEDICO => 'app_pos_operatorio_alertas',
        self::COORDENACAO => 'app_pos_operatorio_relatorios',
    ];

    /**
     * Feature IDs → produto (fallback / documentação).
     *
     * @var array<string, string>
     */
    public const FEATURE_PRODUCT = [
        'trabalho' => 'painel',
        'pacientes' => 'pacientes',
        'protocolos' => 'protocolos',
        'biblioteca' => 'protocolos',
        'retornos' => 'pacientes',
        'agenda' => 'pacientes',
        'atendimento' => 'pacientes',
        'contas' => 'operacao',
        'convenios' => 'operacao',
        'guias' => 'operacao',
        'lotes' => 'operacao',
        'questionarios' => 'questionarios',
        'alertas' => 'alertas',
        'sala_critica' => 'alertas',
        'lembretes' => 'questionarios',
        'plantao' => 'alertas',
        'qualidade' => 'relatorios',
        'contrato_cuidado' => 'protocolos',
        'relatorios' => 'relatorios',
        'carteirinha' => 'operacao',
        'comprovante' => 'operacao',
        'guia_medico' => 'protocolos',
        'portal' => 'portal_paciente',
        'integracoes' => 'configuracoes',
        'compliance' => 'configuracoes',
        'config' => 'configuracoes',
        'produtos' => 'configuracoes',
        'recepcao' => 'operacao',
        'painel_dia' => 'painel',
        'comercial' => 'configuracoes',
        'crm' => 'configuracoes',
    ];

    /** @return list<array{id: string, label: string, acesso: string, products: list<string>}> */
    public static function definitions(): array
    {
        return [
            [
                'id' => self::RECEPCAO,
                'label' => 'Recepção',
                'acesso' => 'Cadastro de pacientes',
                'products' => self::PRODUCTS_BY_ROLE[self::RECEPCAO],
            ],
            [
                'id' => self::ENFERMAGEM,
                'label' => 'Enfermagem',
                'acesso' => 'Triagem e questionários',
                'products' => self::PRODUCTS_BY_ROLE[self::ENFERMAGEM],
            ],
            [
                'id' => self::MEDICO,
                'label' => 'Médico',
                'acesso' => 'Alertas, ficha e protocolos',
                'products' => self::PRODUCTS_BY_ROLE[self::MEDICO],
            ],
            [
                'id' => self::COORDENACAO,
                'label' => 'Coordenação',
                'acesso' => 'Relatórios, CRM comercial e configurações',
                'products' => self::PRODUCTS_BY_ROLE[self::COORDENACAO],
            ],
        ];
    }

    public static function isClinicStaffPerfil(string $perfil): bool
    {
        return \in_array($perfil, self::ALL, true);
    }

    /**
     * @return list<array{id: string, label: string, class: string, nivel: int, description: string}>
     */
    public static function assignableProfiles(): array
    {
        return [
            [
                'id' => self::RECEPCAO,
                'label' => 'Recepção',
                'class' => 'membro',
                'nivel' => 1,
                'description' => 'Cadastro de pacientes, agenda e recepção.',
            ],
            [
                'id' => self::ENFERMAGEM,
                'label' => 'Enfermagem',
                'class' => 'supervisor-equipe',
                'nivel' => 2,
                'description' => 'Triagem, questionários e ficha para acompanhamento.',
            ],
            [
                'id' => self::MEDICO,
                'label' => 'Médico',
                'class' => 'supervisor',
                'nivel' => 3,
                'description' => 'Alertas, ficha do paciente e protocolos.',
            ],
            [
                'id' => self::COORDENACAO,
                'label' => 'Coordenação',
                'class' => 'gestor',
                'nivel' => 5,
                'description' => 'Relatórios, CRM comercial, configurações e política clínica.',
            ],
        ];
    }

    public static function label(string $perfil): string
    {
        foreach (self::definitions() as $def) {
            if ($def['id'] === $perfil) {
                return $def['label'];
            }
        }

        return $perfil;
    }

    public static function acesso(string $perfil): string
    {
        foreach (self::definitions() as $def) {
            if ($def['id'] === $perfil) {
                return $def['acesso'];
            }
        }

        return '';
    }

    /** @return list<string> */
    public static function productsFor(string $perfil): array
    {
        return self::PRODUCTS_BY_ROLE[$perfil] ?? [];
    }

    public static function allowsProduct(string $perfil, string $product): bool
    {
        if ($product === '_hub') {
            return self::productsFor($perfil) !== [];
        }

        return \in_array($product, self::productsFor($perfil), true);
    }

    public static function allowsFeature(string $perfil, string $featureId): bool
    {
        $allowed = self::FEATURES_BY_ROLE[$perfil] ?? [];

        return \in_array($featureId, $allowed, true);
    }

    public static function homeRoute(string $perfil): ?string
    {
        return self::HOME_ROUTE_BY_ROLE[$perfil] ?? null;
    }

    /** @return list<array{perfil: string, acesso: string, products: list<string>}> */
    public static function configList(): array
    {
        $rows = [];
        foreach (self::definitions() as $def) {
            $rows[] = [
                'perfil' => $def['label'],
                'acesso' => $def['acesso'],
                'products' => $def['products'],
            ];
        }

        return $rows;
    }
}
