<?php

namespace App\Config;

/**
 * Hubs planejados (somente landing + sidebar, sem produtos ainda).
 *
 * @phpstan-type PlannedHub array{
 *     id: string,
 *     scope: string,
 *     route: string,
 *     path: string,
 *     label: string,
 *     icon: string,
 *     subtitle: string,
 *     empty_icon: string,
 *     empty_title: string,
 *     empty_text: string,
 * }
 */
final class PlannedHubRegistry
{
    /** @var array<string, string> */
    public const GROUP_LABELS = [
        'negocios' => 'Negócios & Growth',
        'pessoas' => 'Pessoas & Cultura',
        'financas' => 'Finanças & Compliance',
        'dados' => 'Dados & Inteligência',
        'operacoes_ext' => 'Operações & Ativos',
        'tecnologia' => 'Tecnologia',
        'estrategia' => 'Estratégia & Governança',
    ];

    /** @var list<string> */
    public const GROUP_ORDER = [
        'negocios',
        'pessoas',
        'financas',
        'dados',
        'operacoes_ext',
        'tecnologia',
        'estrategia',
    ];

    /** @var array<string, string> hub id => group key */
    public const HUB_GROUP = [
        'comercial' => 'negocios',
        'beneficios' => 'negocios',
        'seguros' => 'negocios',
        'marketing' => 'negocios',
        'parceiros' => 'negocios',
        'customer_success' => 'negocios',
        'expansao' => 'negocios',
        'academy' => 'negocios',
        'clima' => 'pessoas',
        'portal' => 'pessoas',
        'recrutamento' => 'pessoas',
        'comunicacao' => 'pessoas',
        'treinamento_regulatorio' => 'pessoas',
        'terceiros' => 'pessoas',
        'financeiro' => 'financas',
        'compliance' => 'financas',
        'juridico' => 'financas',
        'licitacoes' => 'financas',
        'analytics' => 'dados',
        'integracoes' => 'dados',
        'conhecimento' => 'dados',
        'lakehouse' => 'dados',
        'obras' => 'operacoes_ext',
        'publicidade' => 'operacoes_ext',
        'suprimentos' => 'operacoes_ext',
        'facilities' => 'operacoes_ext',
        'patrimonio' => 'operacoes_ext',
        'qualidade' => 'operacoes_ext',
        'sst' => 'operacoes_ext',
        'saude_ocupacional' => 'operacoes_ext',
        'pmo' => 'operacoes_ext',
        'ti' => 'tecnologia',
        'inovacao' => 'tecnologia',
        'seguranca_info' => 'tecnologia',
        'esg' => 'estrategia',
        'holdings' => 'estrategia',
        'franquias' => 'estrategia',
    ];

    /** @var list<PlannedHub> */
    public const HUBS = [
        [
            'id' => 'comercial',
            'scope' => 'hub_comercial',
            'route' => 'app_comercial',
            'path' => '/comercial',
            'label' => 'Núcleo Comercial',
            'icon' => 'fa-handshake',
            'subtitle' => 'CRM e pipeline de vendas',
            'empty_icon' => 'fa-handshake',
            'empty_title' => 'Núcleo Comercial em desenvolvimento',
            'empty_text' => 'CRM, pipeline de vendas, propostas e contratos comerciais estarão disponíveis em breve.',
        ],
        [
            'id' => 'beneficios',
            'scope' => 'hub_beneficios',
            'route' => 'app_beneficios',
            'path' => '/beneficios',
            'label' => 'Núcleo Benefícios',
            'icon' => 'fa-gift',
            'subtitle' => 'Marketplace corporativo',
            'empty_icon' => 'fa-gift',
            'empty_title' => 'Núcleo Benefícios em desenvolvimento',
            'empty_text' => 'Catálogo de benefícios, adesões e marketplace corporativo estarão disponíveis em breve.',
        ],
        [
            'id' => 'academy',
            'scope' => 'hub_academy',
            'route' => 'app_academy',
            'path' => '/academy',
            'label' => 'Núcleo Academy',
            'icon' => 'fa-graduation-cap',
            'subtitle' => 'Cursos e trilhas',
            'empty_icon' => 'fa-graduation-cap',
            'empty_title' => 'Núcleo Academy em desenvolvimento',
            'empty_text' => 'Cursos, trilhas de aprendizado e certificações estarão disponíveis em breve.',
        ],
        [
            'id' => 'parceiros',
            'scope' => 'hub_parceiros',
            'route' => 'app_parceiros',
            'path' => '/parceiros',
            'label' => 'Núcleo Parceiros',
            'icon' => 'fa-people-group',
            'subtitle' => 'Rede e revenda',
            'empty_icon' => 'fa-people-group',
            'empty_title' => 'Núcleo Parceiros em desenvolvimento',
            'empty_text' => 'Rede de parceiros, revenda white-label e comissionamento estarão disponíveis em breve.',
        ],
        [
            'id' => 'financeiro',
            'scope' => 'hub_financeiro',
            'route' => 'app_financeiro',
            'path' => '/financeiro',
            'label' => 'Núcleo Financeiro',
            'icon' => 'fa-coins',
            'subtitle' => 'Tesouraria e orçamento',
            'empty_icon' => 'fa-coins',
            'empty_title' => 'Núcleo Financeiro em desenvolvimento',
            'empty_text' => 'Orçamento de pessoal, tesouraria e integrações contábeis estarão disponíveis em breve.',
        ],
        [
            'id' => 'compliance',
            'scope' => 'hub_compliance',
            'route' => 'app_compliance',
            'path' => '/compliance',
            'label' => 'Núcleo Compliance',
            'icon' => 'fa-scale-balanced',
            'subtitle' => 'Normas e auditorias',
            'empty_icon' => 'fa-scale-balanced',
            'empty_title' => 'Núcleo Compliance em desenvolvimento',
            'empty_text' => 'eSocial, LGPD, obrigações legais e trilhas de auditoria estarão disponíveis em breve.',
        ],
        [
            'id' => 'analytics',
            'scope' => 'hub_analytics',
            'route' => 'app_analytics',
            'path' => '/analytics',
            'label' => 'Núcleo Analytics',
            'icon' => 'fa-chart-line',
            'subtitle' => 'BI e indicadores',
            'empty_icon' => 'fa-chart-line',
            'empty_title' => 'Núcleo Analytics em desenvolvimento',
            'empty_text' => 'Dashboards executivos, KPIs de RH e inteligência de dados estarão disponíveis em breve.',
        ],
        [
            'id' => 'juridico',
            'scope' => 'hub_juridico',
            'route' => 'app_juridico',
            'path' => '/juridico',
            'label' => 'Núcleo Jurídico',
            'icon' => 'fa-gavel',
            'subtitle' => 'Trabalhista e contratos',
            'empty_icon' => 'fa-gavel',
            'empty_title' => 'Núcleo Jurídico em desenvolvimento',
            'empty_text' => 'Contratos, processos trabalhistas e pareceres jurídicos estarão disponíveis em breve.',
        ],
        [
            'id' => 'clima',
            'scope' => 'hub_clima',
            'route' => 'app_clima',
            'path' => '/clima',
            'label' => 'Núcleo Clima',
            'icon' => 'fa-heart',
            'subtitle' => 'Engajamento e eNPS',
            'empty_icon' => 'fa-heart',
            'empty_title' => 'Núcleo Clima em desenvolvimento',
            'empty_text' => 'Pesquisas de clima, eNPS e planos de engajamento estarão disponíveis em breve.',
        ],
        [
            'id' => 'sst',
            'scope' => 'hub_sst',
            'route' => 'app_sst',
            'path' => '/sst',
            'label' => 'Núcleo SST',
            'icon' => 'fa-hard-hat',
            'subtitle' => 'Saúde e segurança',
            'empty_icon' => 'fa-hard-hat',
            'empty_title' => 'Núcleo SST em desenvolvimento',
            'empty_text' => 'PCMSO, gestão de EPIs e segurança do trabalho estarão disponíveis em breve.',
        ],
        [
            'id' => 'comunicacao',
            'scope' => 'hub_comunicacao',
            'route' => 'app_comunicacao',
            'path' => '/comunicacao',
            'label' => 'Núcleo Comunicação',
            'icon' => 'fa-bullhorn',
            'subtitle' => 'Mural e cultura',
            'empty_icon' => 'fa-bullhorn',
            'empty_title' => 'Núcleo Comunicação em desenvolvimento',
            'empty_text' => 'Comunicados internos, mural da empresa e campanhas culturais estarão disponíveis em breve.',
        ],
        [
            'id' => 'publicidade',
            'scope' => 'hub_publicidade',
            'route' => 'app_publicidade',
            'path' => '/publicidade',
            'label' => 'Núcleo Publicidade',
            'icon' => 'fa-palette',
            'subtitle' => 'Marca, campanhas e criativos',
            'empty_icon' => 'fa-palette',
            'empty_title' => 'Núcleo Publicidade',
            'empty_text' => 'Campanhas, clientes, criativos e métricas de marca em um só lugar.',
        ],
        [
            'id' => 'obras',
            'scope' => 'hub_obras',
            'route' => 'app_engenharia',
            'path' => '/engenharia',
            'label' => 'Núcleo Obras e Projetos',
            'icon' => 'fa-hard-hat',
            'subtitle' => 'Engenharia e execução de obras',
            'empty_icon' => 'fa-hard-hat',
            'empty_title' => 'Núcleo Obras e Projetos',
            'empty_text' => 'Projetos, cronograma, orçamentos e equipes de campo integrados.',
        ],
        [
            'id' => 'portal',
            'scope' => 'hub_portal',
            'route' => 'app_hub_portal',
            'path' => '/portal-colaborador',
            'label' => 'Núcleo Portal do Colaborador',
            'icon' => 'fa-id-badge',
            'subtitle' => 'Autoserviço do colaborador',
            'empty_icon' => 'fa-id-badge',
            'empty_title' => 'Núcleo Portal do Colaborador em desenvolvimento',
            'empty_text' => 'Holerites, férias, comunicados e solicitações em uma experiência unificada para o colaborador.',
        ],
        [
            'id' => 'recrutamento',
            'scope' => 'hub_recrutamento',
            'route' => 'app_hub_recrutamento',
            'path' => '/recrutamento',
            'label' => 'Núcleo Recrutamento',
            'icon' => 'fa-user-tie',
            'subtitle' => 'Seleção e pipeline de talentos',
            'empty_icon' => 'fa-user-tie',
            'empty_title' => 'Núcleo Recrutamento em desenvolvimento',
            'empty_text' => 'Vagas, candidatos, entrevistas e integração com o banco de talentos estarão disponíveis em breve.',
        ],
        [
            'id' => 'esg',
            'scope' => 'hub_esg',
            'route' => 'app_esg',
            'path' => '/esg',
            'label' => 'Núcleo ESG',
            'icon' => 'fa-leaf',
            'subtitle' => 'Sustentabilidade e impacto',
            'empty_icon' => 'fa-leaf',
            'empty_title' => 'Núcleo ESG em desenvolvimento',
            'empty_text' => 'Indicadores ambientais, diversidade, governança e relatórios de sustentabilidade estarão disponíveis em breve.',
        ],
        [
            'id' => 'suprimentos',
            'scope' => 'hub_suprimentos',
            'route' => 'app_suprimentos',
            'path' => '/suprimentos',
            'label' => 'Núcleo Suprimentos',
            'icon' => 'fa-boxes',
            'subtitle' => 'Compras e estoque',
            'empty_icon' => 'fa-boxes',
            'empty_title' => 'Núcleo Suprimentos em desenvolvimento',
            'empty_text' => 'Requisições, cotações, pedidos e controle de estoque para obras e operações estarão disponíveis em breve.',
        ],
        [
            'id' => 'ti',
            'scope' => 'hub_ti',
            'route' => 'app_ti',
            'path' => '/ti',
            'label' => 'Núcleo TI',
            'icon' => 'fa-tower-broadcast',
            'subtitle' => 'NOC Center · Service desk',
            'empty_icon' => 'fa-headset',
            'empty_title' => 'Núcleo TI em desenvolvimento',
            'empty_text' => 'Chamados, inventário de ativos, SLAs e suporte interno estarão disponíveis em breve.',
        ],
        [
            'id' => 'expansao',
            'scope' => 'hub_expansao',
            'route' => 'app_expansao',
            'path' => '/expansao',
            'label' => 'Núcleo Expansão',
            'icon' => 'fa-globe',
            'subtitle' => 'Franquias e novos mercados',
            'empty_icon' => 'fa-globe',
            'empty_title' => 'Núcleo Expansão em desenvolvimento',
            'empty_text' => 'Franquias, unidades, playbooks de expansão e acompanhamento de novos mercados estarão disponíveis em breve.',
        ],
        [
            'id' => 'qualidade',
            'scope' => 'hub_qualidade',
            'route' => 'app_qualidade',
            'path' => '/qualidade',
            'label' => 'Núcleo Qualidade',
            'icon' => 'fa-clipboard-check',
            'subtitle' => 'ISO e auditorias de processo',
            'empty_icon' => 'fa-clipboard-check',
            'empty_title' => 'Núcleo Qualidade em desenvolvimento',
            'empty_text' => 'Gestão ISO, auditorias de processo, não conformidades e planos de ação corretiva estarão disponíveis em breve.',
        ],
        [
            'id' => 'facilities',
            'scope' => 'hub_facilities',
            'route' => 'app_facilities',
            'path' => '/facilities',
            'label' => 'Núcleo Facilities',
            'icon' => 'fa-building',
            'subtitle' => 'Predial, frota e manutenção',
            'empty_icon' => 'fa-building',
            'empty_title' => 'Núcleo Facilities em desenvolvimento',
            'empty_text' => 'Gestão predial, frota, manutenção preventiva e ordens de serviço estarão disponíveis em breve.',
        ],
        [
            'id' => 'patrimonio',
            'scope' => 'hub_patrimonio',
            'route' => 'app_patrimonio',
            'path' => '/patrimonio',
            'label' => 'Núcleo Patrimônio',
            'icon' => 'fa-warehouse',
            'subtitle' => 'Ativos e inventário',
            'empty_icon' => 'fa-warehouse',
            'empty_title' => 'Núcleo Patrimônio em desenvolvimento',
            'empty_text' => 'Inventário de ativos, depreciação, alocação e rastreio patrimonial estarão disponíveis em breve.',
        ],
        [
            'id' => 'conhecimento',
            'scope' => 'hub_conhecimento',
            'route' => 'app_conhecimento',
            'path' => '/conhecimento',
            'label' => 'Núcleo Conhecimento',
            'icon' => 'fa-book',
            'subtitle' => 'Wiki, SOPs e playbooks',
            'empty_icon' => 'fa-book',
            'empty_title' => 'Núcleo Conhecimento em desenvolvimento',
            'empty_text' => 'Base de conhecimento, SOPs, playbooks e documentação operacional estarão disponíveis em breve.',
        ],
        [
            'id' => 'integracoes',
            'scope' => 'hub_integracoes',
            'route' => 'app_integracoes',
            'path' => '/integracoes',
            'label' => 'Núcleo Integrações',
            'icon' => 'fa-plug',
            'subtitle' => 'APIs, conectores e webhooks',
            'empty_icon' => 'fa-plug',
            'empty_title' => 'Núcleo Integrações',
            'empty_text' => 'Central de conectividade da plataforma — catálogo, webhooks, API keys e monitoramento.',
        ],
        [
            'id' => 'customer_success',
            'scope' => 'hub_customer_success',
            'route' => 'app_customer_success',
            'path' => '/customer-success',
            'label' => 'Núcleo Customer Success',
            'icon' => 'fa-hand-holding-heart',
            'subtitle' => 'Pós-venda e retenção',
            'empty_icon' => 'fa-hand-holding-heart',
            'empty_title' => 'Núcleo Customer Success em desenvolvimento',
            'empty_text' => 'Pós-venda, NPS de clientes, health score e planos de retenção estarão disponíveis em breve.',
        ],
        [
            'id' => 'inovacao',
            'scope' => 'hub_inovacao',
            'route' => 'app_inovacao',
            'path' => '/inovacao',
            'label' => 'Núcleo Inovação',
            'icon' => 'fa-lightbulb',
            'subtitle' => 'Labs e experimentos',
            'empty_icon' => 'fa-lightbulb',
            'empty_title' => 'Núcleo Inovação em desenvolvimento',
            'empty_text' => 'POCs, experimentos, backlog de ideias e acompanhamento de inovação estarão disponíveis em breve.',
        ],
        [
            'id' => 'holdings',
            'scope' => 'hub_holdings',
            'route' => 'app_holdings',
            'path' => '/holdings',
            'label' => 'Núcleo Multi-empresa',
            'icon' => 'fa-sitemap',
            'subtitle' => 'Holdings e visão consolidada',
            'empty_icon' => 'fa-sitemap',
            'empty_title' => 'Núcleo Multi-empresa em desenvolvimento',
            'empty_text' => 'Visão consolidada de holdings, filiais, unidades de negócio e indicadores multi-empresa estarão disponíveis em breve.',
        ],
        [
            'id' => 'seguros',
            'scope' => 'hub_seguros',
            'route' => 'app_seguros',
            'path' => '/seguros',
            'label' => 'Núcleo Seguros',
            'icon' => 'fa-umbrella',
            'subtitle' => 'Seguros e benefícios corporativos',
            'empty_icon' => 'fa-umbrella',
            'empty_title' => 'Núcleo Seguros em desenvolvimento',
            'empty_text' => 'Apólices, coberturas, sinistros e benefícios corporativos integrados à folha e ao colaborador estarão disponíveis em breve.',
        ],
        [
            'id' => 'saude_ocupacional',
            'scope' => 'hub_saude_ocupacional',
            'route' => 'app_saude_ocupacional',
            'path' => '/saude-ocupacional',
            'label' => 'Núcleo Saúde Ocupacional',
            'icon' => 'fa-stethoscope',
            'subtitle' => 'PCMSO, exames e medicina do trabalho',
            'empty_icon' => 'fa-stethoscope',
            'empty_title' => 'Núcleo Saúde Ocupacional em desenvolvimento',
            'empty_text' => 'PCMSO, agendamento de exames, ASO, afastamentos e prontuário ocupacional estarão disponíveis em breve.',
        ],
        [
            'id' => 'licitacoes',
            'scope' => 'hub_licitacoes',
            'route' => 'app_licitacoes',
            'path' => '/licitacoes',
            'label' => 'Núcleo Licitações',
            'icon' => 'fa-landmark',
            'subtitle' => 'Contratos públicos e B2G',
            'empty_icon' => 'fa-landmark',
            'empty_title' => 'Núcleo Licitações em desenvolvimento',
            'empty_text' => 'Editais, propostas, contratos públicos e acompanhamento de obrigações B2G estarão disponíveis em breve.',
        ],
        [
            'id' => 'marketing',
            'scope' => 'hub_marketing',
            'route' => 'app_marketing',
            'path' => '/marketing',
            'label' => 'Núcleo Marketing',
            'icon' => 'fa-bullseye',
            'subtitle' => 'Demand gen, leads e campanhas',
            'empty_icon' => 'fa-bullseye',
            'empty_title' => 'Núcleo Marketing em desenvolvimento',
            'empty_text' => 'Geração de demanda, leads, funis de conversão e campanhas de performance estarão disponíveis em breve.',
        ],
        [
            'id' => 'lakehouse',
            'scope' => 'hub_lakehouse',
            'route' => 'app_lakehouse',
            'path' => '/lakehouse',
            'label' => 'Núcleo Data & Lakehouse',
            'icon' => 'fa-database',
            'subtitle' => 'Dados brutos e pipelines',
            'empty_icon' => 'fa-database',
            'empty_title' => 'Núcleo Data & Lakehouse em desenvolvimento',
            'empty_text' => 'Ingestão de dados brutos, catálogo, pipelines e camadas de lakehouse estarão disponíveis em breve.',
        ],
        [
            'id' => 'franquias',
            'scope' => 'hub_franquias',
            'route' => 'app_franquias',
            'path' => '/franquias',
            'label' => 'Núcleo Franquias & Unidades',
            'icon' => 'fa-store',
            'subtitle' => 'Rede de unidades e franqueados',
            'empty_icon' => 'fa-store',
            'empty_title' => 'Núcleo Franquias & Unidades em desenvolvimento',
            'empty_text' => 'Unidades, franqueados, royalties, indicadores por loja e playbooks de rede estarão disponíveis em breve.',
        ],
        [
            'id' => 'seguranca_info',
            'scope' => 'hub_seguranca_info',
            'route' => 'app_seguranca_info',
            'path' => '/seguranca-informacao',
            'label' => 'Núcleo Segurança da Informação',
            'icon' => 'fa-user-shield',
            'subtitle' => 'LGPD técnica e incidentes',
            'empty_icon' => 'fa-user-shield',
            'empty_title' => 'Núcleo Segurança da Informação em desenvolvimento',
            'empty_text' => 'Gestão de incidentes, vulnerabilidades, controles técnicos de LGPD e políticas de segurança estarão disponíveis em breve.',
        ],
        [
            'id' => 'pmo',
            'scope' => 'hub_pmo',
            'route' => 'app_pmo',
            'path' => '/pmo',
            'label' => 'Núcleo PMO',
            'icon' => 'fa-diagram-project',
            'subtitle' => 'Projetos internos e governança',
            'empty_icon' => 'fa-diagram-project',
            'empty_title' => 'Núcleo PMO em desenvolvimento',
            'empty_text' => 'Portfólio de projetos internos, governança PMO, status reports e alocação de recursos estarão disponíveis em breve.',
        ],
        [
            'id' => 'treinamento_regulatorio',
            'scope' => 'hub_treinamento_regulatorio',
            'route' => 'app_treinamento_regulatorio',
            'path' => '/treinamento-regulatorio',
            'label' => 'Núcleo Treinamento Regulatório',
            'icon' => 'fa-certificate',
            'subtitle' => 'NR, certificações e obrigações',
            'empty_icon' => 'fa-certificate',
            'empty_title' => 'Núcleo Treinamento Regulatório em desenvolvimento',
            'empty_text' => 'Normas regulamentadoras, certificações obrigatórias, reciclagens e comprovantes de treinamento estarão disponíveis em breve.',
        ],
        [
            'id' => 'terceiros',
            'scope' => 'hub_terceiros',
            'route' => 'app_terceiros',
            'path' => '/terceiros',
            'label' => 'Núcleo Gestão de Terceiros',
            'icon' => 'fa-user-clock',
            'subtitle' => 'PJ, fornecedores e mão de obra',
            'empty_icon' => 'fa-user-clock',
            'empty_title' => 'Núcleo Gestão de Terceiros em desenvolvimento',
            'empty_text' => 'Cadastro de terceiros, contratos PJ, documentação de fornecedores e controle de mão de obra terceirizada estarão disponíveis em breve.',
        ],
    ];

    public static function findById(string $id): ?array
    {
        foreach (self::HUBS as $hub) {
            if ($hub['id'] === $id) {
                return $hub;
            }
        }

        return null;
    }

    public static function findByRoute(?string $route): ?array
    {
        if ($route === null || $route === '') {
            return null;
        }

        foreach (self::HUBS as $hub) {
            if (str_starts_with($route, $hub['route'])) {
                return $hub;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function routePrefixes(): array
    {
        return array_column(self::HUBS, 'route');
    }

    /** @return list<string> */
    public static function scopes(): array
    {
        return array_column(self::HUBS, 'scope');
    }

    public static function groupFor(string $hubId): string
    {
        return self::HUB_GROUP[$hubId] ?? 'estrategia';
    }

    /**
     * @param list<PlannedHub> $hubs
     *
     * @return list<array{key: string, label: string, hubs: list<PlannedHub>}>
     */
    public static function groupHubs(array $hubs): array
    {
        $buckets = [];
        foreach ($hubs as $hub) {
            $key = self::groupFor($hub['id']);
            $buckets[$key][] = $hub;
        }

        $out = [];
        foreach (self::GROUP_ORDER as $key) {
            if (empty($buckets[$key])) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => self::GROUP_LABELS[$key],
                'hubs' => $buckets[$key],
            ];
            unset($buckets[$key]);
        }

        foreach ($buckets as $key => $items) {
            $out[] = [
                'key' => $key,
                'label' => self::GROUP_LABELS[$key] ?? $key,
                'hubs' => $items,
            ];
        }

        return $out;
    }
}
