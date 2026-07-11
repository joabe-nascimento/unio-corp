<?php

namespace App\PosOperatorio;

/**
 * Catálogo unificado de capacidades da UNIO SAÚDE — sidebar, dashboard e integrações.
 */
final class ClinicFeatureCatalog
{
    public const GROUP_OPERACAO = 'operacao';
    public const GROUP_CLINICA = 'clinica';
    public const GROUP_MONITORAMENTO = 'monitoramento';
    public const GROUP_INTELIGENCIA = 'inteligencia';
    public const GROUP_EXPERIENCIA = 'experiencia';
    public const GROUP_SISTEMA = 'sistema';

    /** @return list<array{key: string, label: string}> */
    public static function sidebarSections(): array
    {
        return [
            ['key' => self::GROUP_OPERACAO, 'label' => 'Pós-operatório'],
            ['key' => self::GROUP_CLINICA, 'label' => 'Clínica'],
            ['key' => self::GROUP_MONITORAMENTO, 'label' => 'Monitoramento'],
            ['key' => self::GROUP_INTELIGENCIA, 'label' => 'Inteligência'],
            ['key' => self::GROUP_EXPERIENCIA, 'label' => 'Experiência do paciente'],
            ['key' => self::GROUP_SISTEMA, 'label' => 'Sistema'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            [
                'id' => 'trabalho',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_OPERACAO,
                'route' => 'app_pos_operatorio_trabalho',
                'route_prefix' => 'app_pos_operatorio_trabalho',
                'icon' => 'fa-list-check',
                'title' => 'O que fazer agora',
                'short' => 'Fila do dia',
                'desc' => 'P1, questionários pendentes e retornos em uma única fila.',
                'status' => 'active',
                'badge_key' => 'precisa_acao',
                'tone' => 'amber',
            ],
            [
                'id' => 'pacientes',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_CLINICA,
                'route' => 'app_pos_operatorio_pacientes',
                'route_prefix' => 'app_pos_operatorio_paciente',
                'icon' => 'fa-user-injured',
                'title' => 'Pacientes',
                'short' => 'Pacientes',
                'desc' => 'Cadastro e ficha pós-cirúrgica.',
                'status' => 'active',
                'badge_key' => 'pacientes_ativos',
                'tone' => 'sky',
            ],
            [
                'id' => 'protocolos',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_CLINICA,
                'route' => 'app_pos_operatorio_protocolos',
                'route_prefix' => 'app_pos_operatorio_protocolo',
                'icon' => 'fa-file-medical',
                'title' => 'Protocolos',
                'short' => 'Protocolos',
                'desc' => 'Checklists e regras de alerta.',
                'status' => 'active',
                'tone' => 'sage',
            ],
            [
                'id' => 'biblioteca',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_CLINICA,
                'route' => 'app_pos_operatorio_biblioteca',
                'route_prefix' => 'app_pos_operatorio_biblioteca',
                'icon' => 'fa-book-medical',
                'title' => 'Biblioteca',
                'short' => 'Biblioteca',
                'desc' => 'Modelos prontos por procedimento.',
                'status' => 'active',
                'tone' => 'lavender',
            ],
            [
                'id' => 'retornos',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_CLINICA,
                'route' => 'app_pos_operatorio_retornos',
                'route_prefix' => 'app_pos_operatorio_retornos',
                'icon' => 'fa-calendar-check',
                'title' => 'Retornos',
                'short' => 'Retornos',
                'desc' => 'Marcos D+7, D+14 e consultas previstas.',
                'status' => 'active',
                'tone' => 'teal',
            ],
            [
                'id' => 'questionarios',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_MONITORAMENTO,
                'route' => 'app_pos_operatorio_questionarios',
                'route_prefix' => 'app_pos_operatorio_questionario',
                'icon' => 'fa-clipboard-list',
                'title' => 'Questionários',
                'short' => 'Questionários',
                'desc' => 'Respostas diárias do portal.',
                'status' => 'active',
                'badge_key' => 'questionarios',
                'tone' => 'amber',
            ],
            [
                'id' => 'alertas',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_MONITORAMENTO,
                'route' => 'app_pos_operatorio_alertas',
                'route_prefix' => 'app_pos_operatorio_alerta',
                'icon' => 'fa-triangle-exclamation',
                'title' => 'Alertas',
                'short' => 'Alertas',
                'desc' => 'Fila P1–P4 com SLA.',
                'status' => 'active',
                'badge_key' => 'alertas',
                'tone' => 'rose',
            ],
            [
                'id' => 'sala_critica',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_MONITORAMENTO,
                'route' => 'app_pos_operatorio_sala_critica',
                'route_prefix' => 'app_pos_operatorio_sala_critica',
                'icon' => 'fa-bed-pulse',
                'title' => 'Sala crítica',
                'short' => 'Sala crítica',
                'desc' => 'War room para P1.',
                'status' => 'active',
                'badge_key' => 'sala_critica',
                'tone' => 'rose',
            ],
            [
                'id' => 'lembretes',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_MONITORAMENTO,
                'route' => 'app_pos_operatorio_lembretes',
                'route_prefix' => 'app_pos_operatorio_lembretes',
                'icon' => 'fa-bell',
                'title' => 'Lembretes',
                'short' => 'Lembretes',
                'desc' => 'Cobrança automática de questionários.',
                'status' => 'active',
                'tone' => 'amber',
            ],
            [
                'id' => 'plantao',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_MONITORAMENTO,
                'route' => 'app_pos_operatorio_plantao',
                'route_prefix' => 'app_pos_operatorio_plantao',
                'icon' => 'fa-user-clock',
                'title' => 'Plantão',
                'short' => 'Plantão',
                'desc' => 'Escala e roteamento de P1.',
                'status' => 'active',
                'tone' => 'lavender',
            ],
            [
                'id' => 'qualidade',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_INTELIGENCIA,
                'route' => 'app_pos_operatorio_qualidade',
                'route_prefix' => 'app_pos_operatorio_qualidade',
                'icon' => 'fa-chart-pie',
                'title' => 'Qualidade',
                'short' => 'Qualidade',
                'desc' => 'Taxa de resposta, SLA e heatmap.',
                'status' => 'active',
                'tone' => 'sage',
            ],
            [
                'id' => 'relatorios',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_INTELIGENCIA,
                'route' => 'app_pos_operatorio_relatorios',
                'route_prefix' => 'app_pos_operatorio_relatorios',
                'icon' => 'fa-file-export',
                'title' => 'Relatórios',
                'short' => 'Relatórios',
                'desc' => 'Exportação e auditoria clínica.',
                'status' => 'active',
                'tone' => 'sky',
            ],
            [
                'id' => 'carteirinha',
                'product' => ClinicProductCatalog::CARTEIRINHA,
                'group' => self::GROUP_EXPERIENCIA,
                'route' => 'app_pos_operatorio_carteirinha',
                'route_prefix' => 'app_pos_operatorio_carteirinha',
                'icon' => 'fa-id-card',
                'title' => 'Carteirinha digital',
                'short' => 'Carteirinha',
                'desc' => 'Emissão com foto, QR e validação na recepção.',
                'status' => 'active',
                'tone' => 'lavender',
            ],
            [
                'id' => 'guia_medico',
                'product' => ClinicProductCatalog::GUIA_MEDICO,
                'group' => self::GROUP_EXPERIENCIA,
                'route' => 'app_pos_operatorio_guia_medico',
                'route_prefix' => 'app_pos_operatorio_guia_medico',
                'icon' => 'fa-book-medical',
                'title' => 'Guia médico',
                'short' => 'Guia médico',
                'desc' => 'Editor de orientações exibidas no portal.',
                'status' => 'active',
                'tone' => 'sage',
            ],
            [
                'id' => 'portal',
                'product' => null,
                'group' => self::GROUP_SISTEMA,
                'route' => 'app_portal_patient_login',
                'route_prefix' => 'app_portal_patient',
                'icon' => 'fa-person-circle-check',
                'title' => 'Portal do paciente',
                'short' => 'Portal',
                'desc' => 'Onboarding e questionário diário.',
                'status' => 'active',
                'external' => true,
                'tone' => 'teal',
            ],
            [
                'id' => 'integracoes',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_SISTEMA,
                'route' => 'app_pos_operatorio_integracoes',
                'route_prefix' => 'app_pos_operatorio_integracoes',
                'icon' => 'fa-plug',
                'title' => 'Integrações',
                'short' => 'Integrações',
                'desc' => 'WhatsApp, PEP, calendário e webhooks.',
                'status' => 'active',
                'tone' => 'lavender',
            ],
            [
                'id' => 'compliance',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_SISTEMA,
                'route' => 'app_pos_operatorio_compliance',
                'route_prefix' => 'app_pos_operatorio_compliance',
                'icon' => 'fa-shield-halved',
                'title' => 'LGPD',
                'short' => 'LGPD',
                'desc' => 'Consentimento, auditoria e retenção.',
                'status' => 'active',
                'tone' => 'sage',
            ],
            [
                'id' => 'config',
                'product' => ClinicProductCatalog::POS_OPERATORIO,
                'group' => self::GROUP_SISTEMA,
                'route' => 'app_pos_operatorio_config',
                'route_prefix' => 'app_pos_operatorio_config',
                'icon' => 'fa-sliders',
                'title' => 'Configurações',
                'short' => 'Configurações',
                'desc' => 'SLA, canais e regras da clínica.',
                'status' => 'active',
                'tone' => 'sky',
            ],
            [
                'id' => 'produtos',
                'product' => null,
                'group' => self::GROUP_SISTEMA,
                'route' => 'app_pos_operatorio_produtos',
                'route_prefix' => 'app_pos_operatorio_produtos',
                'icon' => 'fa-layer-group',
                'title' => 'Produtos da plataforma',
                'short' => 'Produtos',
                'desc' => 'Ativar pós-operatório, carteirinha e guia médico.',
                'status' => 'active',
                'tone' => 'lavender',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $features
     * @param array<string, bool>       $enabledProducts
     *
     * @return list<array<string, mixed>>
     */
    public static function filterByProducts(array $features, array $enabledProducts): array
    {
        return array_values(array_filter(
            $features,
            static function (array $feature) use ($enabledProducts): bool {
                $product = $feature['product'] ?? null;
                if ($product === null || $product === '') {
                    return true;
                }

                return $enabledProducts[$product] ?? true;
            },
        ));
    }

    /**
     * @param list<array<string, mixed>> $features
     *
     * @return list<array{key: string, label: string}>
     */
    public static function sectionsForFeatures(array $features): array
    {
        $groups = [];
        foreach ($features as $feature) {
            $groups[(string) ($feature['group'] ?? '')] = true;
        }

        return array_values(array_filter(
            self::sidebarSections(),
            static fn (array $section): bool => isset($groups[$section['key']]),
        ));
    }

    /** @return array<string, bool> */
    public static function defaultProductEnabledMap(): array
    {
        return ClinicProductCatalog::defaultEnabledMap();
    }

    /** @return list<array<string, mixed>> */
    public static function forGroup(string $group): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $f): bool => ($f['group'] ?? '') === $group,
        ));
    }

    /** @return list<array<string, mixed>> */
    public static function integrations(): array
    {
        return [
            ['id' => 'whatsapp', 'nome' => 'Lembretes por telefone', 'status' => 'active', 'desc' => 'Questionário diário e retorno via SMS/WhatsApp manual.', 'route' => 'app_pos_operatorio_lembretes', 'route_label' => 'Ver lembretes'],
            ['id' => 'pep', 'nome' => 'Prontuário / PEP', 'status' => 'configurable', 'desc' => 'Importação automática via webhook ou cadastro manual na ficha.', 'route' => 'app_pos_operatorio_pacientes', 'route_label' => 'Cadastrar paciente'],
            ['id' => 'calendar', 'nome' => 'Agenda de retornos', 'status' => 'active', 'desc' => 'Retornos pós-operatórios e escala na clínica.', 'route' => 'app_pos_operatorio_retornos', 'route_label' => 'Ver retornos'],
            ['id' => 'vitoria', 'nome' => 'Vitória AI', 'status' => 'active', 'desc' => 'Triagem, resumo de ficha e sugestão de conduta.', 'route' => null, 'route_label' => null],
            ['id' => 'mercure', 'nome' => 'Tempo real (Mercure)', 'status' => 'active', 'desc' => 'Atualização live da fila de alertas.', 'route' => 'app_pos_operatorio_alertas', 'route_label' => 'Fila de alertas'],
            ['id' => 'webhook', 'nome' => 'Webhooks', 'status' => 'configurable', 'desc' => 'Notificações de alerta P1 e questionário pendente para sistemas externos.', 'route' => null, 'route_label' => null],
        ];
    }

    /** @param array<string, mixed> $feature */
    public static function isRouteActive(?string $route, array $feature): bool
    {
        if ($route === null || $route === '') {
            return false;
        }
        $featureRoute = (string) ($feature['route'] ?? '');
        $prefix = (string) ($feature['route_prefix'] ?? $featureRoute);

        return $route === $featureRoute || str_starts_with($route, $prefix);
    }
}
