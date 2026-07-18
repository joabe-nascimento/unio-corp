<?php

namespace App\Service;

use App\Service\Organismo\OrganismoRedirectService;

/**
 * Resolve a rota "voltar" para telas filhas de um hub (RH, Pessoas, Engenharia, etc.).
 */
final class PageBackResolver
{
    public function __construct(
        private OrganismoRedirectService $organismoRedirects,
    ) {
    }

    /** @var array<string, string> hub index route => parent hub route */
    private const HUB_PARENT = [
        'app_hub_operacoes' => 'app_dashboard',
        'app_rh' => 'app_hub_operacoes',
        'app_pessoas' => 'app_hub_operacoes',
        'app_engenharia' => 'app_hub_operacoes',
        'app_talentos' => 'app_dashboard',
        'app_maturidade' => 'app_dashboard',
        'app_recrutamento' => 'app_dashboard',
        'app_recrutamento_vagas' => 'app_recrutamento',
        'app_recrutamento_vagas_show' => 'app_recrutamento_vagas',
        'app_recrutamento_candidatos' => 'app_recrutamento',
        'app_recrutamento_candidatos_show' => 'app_recrutamento_candidatos',
        'app_recrutamento_pipeline' => 'app_recrutamento',
        'app_recrutamento_analytics' => 'app_recrutamento',
        'app_recrutamento_carreiras' => 'app_recrutamento',
        'app_recrutamento_talentos' => 'app_recrutamento',
        'app_recrutamento_integracoes' => 'app_recrutamento',
        'app_comercial' => 'app_dashboard',
        'app_comercial_leads' => 'app_comercial',
        'app_comercial_lead_show' => 'app_comercial_leads',
        'app_comercial_pipeline' => 'app_comercial',
        'app_comercial_oportunidade_show' => 'app_comercial_pipeline',
        'app_comercial_clientes' => 'app_comercial',
        'app_comercial_cliente_show' => 'app_comercial_clientes',
        'app_comercial_atividades' => 'app_comercial',
        'app_comercial_analytics' => 'app_comercial',
        'app_beneficios' => 'app_dashboard',
        'app_academy' => 'app_dashboard',
        'app_parceiros' => 'app_dashboard',
        'app_financeiro' => 'app_dashboard',
        'app_compliance' => 'app_dashboard',
        'app_analytics' => 'app_dashboard',
        'app_juridico' => 'app_dashboard',
        'app_clima' => 'app_dashboard',
        'app_sst' => 'app_dashboard',
        'app_comunicacao' => 'app_dashboard',
        'app_hub_portal' => 'app_dashboard',
        'app_esg' => 'app_dashboard',
        'app_suprimentos' => 'app_dashboard',
        'app_ti' => 'app_dashboard',
        'app_ti_chamados' => 'app_ti',
        'app_ti_meus_chamados' => 'app_ti',
        'app_ti_chamado_novo' => 'app_ti_chamados',
        'app_ti_chamado_show' => 'app_ti_chamados',
        'app_ti_chamado_status' => 'app_ti_chamado_show',
        'app_ti_chamado_excluir' => 'app_ti_chamados',
        'app_ti_chamado_atribuir' => 'app_ti_chamado_show',
        'app_ti_chamado_nota' => 'app_ti_chamado_show',
        'app_ti_chamado_prioridade' => 'app_ti_chamado_show',
        'app_ti_chamado_helia_aplicar' => 'app_ti_chamado_show',
        'app_ti_chamado_helia_revisar' => 'app_ti_cortex',
        'app_ti_ativos' => 'app_ti',
        'app_ti_ativo_novo_submit' => 'app_ti_ativos',
        'app_ti_ativo_editar_submit' => 'app_ti_ativos',
        'app_ti_ativo_excluir' => 'app_ti_ativos',
        'app_ti_licencas' => 'app_ti',
        'app_ti_licenca_novo_submit' => 'app_ti_licencas',
        'app_ti_licenca_editar_submit' => 'app_ti_licencas',
        'app_ti_licenca_excluir' => 'app_ti_licencas',
        'app_ti_sla' => 'app_ti',
        'app_ti_manutencoes' => 'app_ti',
        'app_ti_manutencao_novo_submit' => 'app_ti_manutencoes',
        'app_ti_manutencao_editar_submit' => 'app_ti_manutencoes',
        'app_ti_manutencao_excluir' => 'app_ti_manutencoes',
        'app_ti_catalogo' => 'app_ti',
        'app_ti_integracoes' => 'app_ti',
        'app_ti_integracao_novo_submit' => 'app_ti_integracoes',
        'app_ti_integracao_editar_submit' => 'app_ti_integracoes',
        'app_ti_integracao_excluir' => 'app_ti_integracoes',
        'app_ti_cortex' => 'app_ti',
        'app_ti_analytics' => 'app_ti',
        'app_ti_novidades' => 'app_ti',
        'app_ti_novidade_novo_submit' => 'app_ti_novidades',
        'app_ti_novidade_editar_submit' => 'app_ti_novidades',
        'app_ti_novidade_excluir' => 'app_ti_novidades',
        'app_ti_kb' => 'app_ti',
        'app_ti_kb_novo_submit' => 'app_ti_kb',
        'app_ti_kb_editar_submit' => 'app_ti_kb',
        'app_ti_kb_excluir' => 'app_ti_kb',
        'app_ti_problemas' => 'app_ti',
        'app_ti_problema_novo_submit' => 'app_ti_problemas',
        'app_ti_problema_editar_submit' => 'app_ti_problemas',
        'app_ti_problema_excluir' => 'app_ti_problemas',
        'app_ti_catalogo_novo' => 'app_ti_catalogo',
        'app_ti_catalogo_editar' => 'app_ti_catalogo',
        'app_ti_catalogo_excluir' => 'app_ti_catalogo',
        'app_ti_manutencao_aprovar' => 'app_ti_manutencoes',
        'app_expansao' => 'app_dashboard',
        'app_qualidade' => 'app_dashboard',
        'app_facilities' => 'app_dashboard',
        'app_patrimonio' => 'app_dashboard',
        'app_conhecimento' => 'app_dashboard',
        'app_integracoes' => 'app_dashboard',
        'app_integracoes_catalogo' => 'app_integracoes',
        'app_integracoes_conectores' => 'app_integracoes',
        'app_integracoes_webhooks' => 'app_integracoes',
        'app_integracoes_mapeamentos' => 'app_integracoes',
        'app_integracoes_api' => 'app_integracoes',
        'app_integracoes_logs' => 'app_integracoes',
        'app_integracoes_observatorio' => 'app_integracoes',
        'app_integracoes_playbooks' => 'app_integracoes',
        'app_customer_success' => 'app_dashboard',
        'app_inovacao' => 'app_dashboard',
        'app_inovacao_pipeline' => 'app_inovacao',
        'app_inovacao_laboratorio' => 'app_inovacao',
        'app_inovacao_experimentos' => 'app_inovacao',
        'app_inovacao_backlog' => 'app_inovacao',
        'app_inovacao_analytics' => 'app_inovacao',
        'app_inovacao_conexoes' => 'app_inovacao',
        'app_inovacao_impact'     => 'app_inovacao',
        'app_inovacao_tendencias' => 'app_inovacao',
        'app_inovacao_portfolio'  => 'app_inovacao',
        'app_inovacao_novidades'  => 'app_inovacao',
        'app_inovacao_nova_ideia' => 'app_inovacao_backlog',
        'app_inovacao_ideia_show' => 'app_inovacao_backlog',
        'app_inovacao_ideia_editar' => 'app_inovacao_ideia_show',
        'app_inovacao_conexao_nova' => 'app_inovacao_conexoes',
        'app_inovacao_conexao_editar' => 'app_inovacao_conexoes',
        'app_inovacao_tendencia_nova' => 'app_inovacao_tendencias',
        'app_inovacao_tendencia_editar' => 'app_inovacao_tendencias',
        'app_inovacao_novidade_nova' => 'app_inovacao_novidades',
        'app_inovacao_novidade_editar' => 'app_inovacao_novidades',
        'app_inovacao_impact_nova' => 'app_inovacao_impact',
        'app_inovacao_impact_editar' => 'app_inovacao_impact',
        'app_holdings' => 'app_dashboard',
        'app_seguros' => 'app_dashboard',
        'app_saude_ocupacional' => 'app_dashboard',
        'app_pos_operatorio' => 'app_dashboard',
        'app_pos_operatorio_alertas' => 'app_dashboard',
        'app_pos_operatorio_sala_critica' => 'app_dashboard',
        'app_pos_operatorio_pacientes' => 'app_dashboard',
        'app_pos_operatorio_paciente_novo' => 'app_pos_operatorio_pacientes',
        'app_pos_operatorio_paciente_show' => 'app_pos_operatorio_pacientes',
        'app_pos_operatorio_paciente_editar' => 'app_pos_operatorio_pacientes',
        'app_pos_operatorio_protocolos' => 'app_dashboard',
        'app_pos_operatorio_protocolo_novo' => 'app_pos_operatorio_protocolos',
        'app_pos_operatorio_protocolo_editar' => 'app_pos_operatorio_protocolos',
        'app_pos_operatorio_questionarios' => 'app_dashboard',
        'app_pos_operatorio_trabalho' => 'app_dashboard',
        'app_pos_operatorio_qualidade' => 'app_dashboard',
        'app_pos_operatorio_outcomes' => 'app_dashboard',
        'app_pos_operatorio_retornos' => 'app_dashboard',
        'app_pos_operatorio_biblioteca' => 'app_dashboard',
        'app_pos_operatorio_lembretes' => 'app_dashboard',
        'app_pos_operatorio_plantao' => 'app_dashboard',
        'app_pos_operatorio_relatorios' => 'app_dashboard',
        'app_pos_operatorio_integracoes' => 'app_dashboard',
        'app_pos_operatorio_compliance' => 'app_dashboard',
        'app_pos_operatorio_config' => 'app_dashboard',
        'app_clinic_pacientes' => 'app_dashboard',
        'app_clinic_sala_critica' => 'app_dashboard',
        'app_clinic_alertas' => 'app_dashboard',
        'app_clinic_protocolos' => 'app_dashboard',
        'app_clinic_questionarios' => 'app_dashboard',
        'app_clinic_portal' => 'app_dashboard',
        'app_clinic_retornos' => 'app_dashboard',
        'app_clinic_paciente_show' => 'app_clinic_pacientes',
        'app_medico' => 'app_dashboard',
        'app_medico_pacientes' => 'app_dashboard',
        'app_medico_sala_critica' => 'app_dashboard',
        'app_medico_alertas' => 'app_dashboard',
        'app_medico_protocolos' => 'app_dashboard',
        'app_medico_questionarios' => 'app_dashboard',
        'app_medico_retornos' => 'app_dashboard',
        'app_medico_trabalho' => 'app_dashboard',
        'app_medico_carteirinha' => 'app_dashboard',
        'app_medico_guia_medico' => 'app_dashboard',
        'app_medico_paciente_show' => 'app_medico_pacientes',
        'app_licitacoes' => 'app_dashboard',
        'app_marketing' => 'app_dashboard',
        'app_lakehouse' => 'app_dashboard',
        'app_franquias' => 'app_dashboard',
        'app_seguranca_info' => 'app_dashboard',
        'app_pmo' => 'app_dashboard',
        'app_treinamento_regulatorio' => 'app_dashboard',
        'app_terceiros' => 'app_dashboard',
        'app_publicidade' => 'app_dashboard',
        'app_admin' => 'app_dashboard',
        'app_admin_configuracoes' => 'app_admin',
        'app_admin_usuarios' => 'app_admin',
        'app_admin_empresas' => 'app_admin',
        'app_admin_operacoes' => 'app_admin',
        'app_welcome_news_show' => 'app_welcome',
        'app_pessoas_equipe_detalhe' => 'app_pessoas_equipes',
        'app_pessoas_membro_ficha' => 'app_pessoas_membros',
        'app_pessoas_cargo_editar' => 'app_pessoas_cargos',
        'app_pessoas_equipe_nova' => 'app_pessoas_equipes',
        'app_pessoas_equipe_editar' => 'app_pessoas_equipe_detalhe',
        'app_pessoas_membro_novo' => 'app_pessoas_membros',
        'app_pessoas_membro_editar' => 'app_pessoas_membro_ficha',
        'app_rh_admissao_show' => 'app_rh_admissoes',
        'app_rh_demissao_show' => 'app_rh_demissoes',
        'app_rh_funcionario_show' => 'app_rh_funcionarios',
        'app_cortex' => 'app_dashboard',
    ];

    /** segmento da rota de detalhe => rota da listagem */
    private const RH_LIST_PARENT = [
        'funcionario' => 'app_rh_funcionarios',
        'admissao' => 'app_rh_admissoes',
        'admissoes' => 'app_rh_admissoes',
        'demissao' => 'app_rh_demissoes',
        'demissoes' => 'app_rh_demissoes',
        'ferias' => 'app_rh_ferias',
        'folha' => 'app_rh_folha',
    ];

    /**
     * @param array<string, mixed> $routeParams
     *
     * @return array{route: string, params: array<string, mixed>}|null
     */
    public function resolve(?string $currentRoute, array $routeParams = []): ?array
    {
        if ($currentRoute === null || $currentRoute === '') {
            return null;
        }

        if (isset(self::HUB_PARENT[$currentRoute])) {
            $parentRoute = $this->normalizeRoute(self::HUB_PARENT[$currentRoute]);

            return [
                'route' => $parentRoute,
                'params' => $this->inheritRouteParams($parentRoute, $routeParams),
            ];
        }

        if ($currentRoute === 'app_core_projetos_show') {
            return ['route' => 'app_core_projetos', 'params' => ['view' => 'lista']];
        }

        if (str_starts_with($currentRoute, 'app_rh_portal')) {
            if ($currentRoute === 'app_rh_portal') {
                return ['route' => 'app_rh', 'params' => []];
            }

            return ['route' => 'app_rh_portal', 'params' => []];
        }

        if (str_starts_with($currentRoute, 'app_rh_')) {
            return $this->resolveRh($currentRoute);
        }

        if (str_starts_with($currentRoute, 'app_admin')) {
            return null;
        }

        if (str_starts_with($currentRoute, 'app_recrutamento_')) {
            return ['route' => 'app_recrutamento', 'params' => []];
        }

        if (str_starts_with($currentRoute, 'app_pessoas_')) {
            return $this->resolvePrefixedHub('app_pessoas', $currentRoute, [
                'membro' => 'app_pessoas_membros',
                'equipe' => 'app_pessoas_equipes',
            ]);
        }

        if (str_starts_with($currentRoute, 'app_engenharia_')) {
            return $this->resolvePrefixedHub('app_engenharia', $currentRoute, []);
        }

        foreach ([
            'app_ti_' => 'app_ti',
            'app_integracoes_' => 'app_integracoes',
            'app_inovacao_' => 'app_inovacao',
        ] as $prefix => $hubRoute) {
            if (str_starts_with($currentRoute, $prefix)) {
                return ['route' => $hubRoute, 'params' => []];
            }
        }

        return null;
    }

    /**
     * @return array{route: string, params: array<string, mixed>}|null
     */
    private function resolveRh(string $route): ?array
    {
        if ($route === 'app_rh_esocial_retry') {
            return ['route' => 'app_rh_esocial', 'params' => []];
        }

        if ($route === 'app_rh_recrutamento_pipeline') {
            return ['route' => 'app_recrutamento_pipeline', 'params' => []];
        }

        if ($route === 'app_rh_recrutamento' || $route === 'app_rh_recrutamento_candidato') {
            return ['route' => 'app_recrutamento_vagas', 'params' => []];
        }

        if (preg_match('/^app_rh_(.+?)_(nova|show|editar|gerar|export|candidato)$/', $route, $m)) {
            $parent = self::RH_LIST_PARENT[$m[1]] ?? null;
            if ($parent !== null) {
                return ['route' => $parent, 'params' => []];
            }
        }

        if ($route !== 'app_rh') {
            return ['route' => 'app_rh', 'params' => []];
        }

        return null;
    }

    /**
     * @param array<string, string> $detailParents
     *
     * @return array{route: string, params: array<string, mixed>}|null
     */
    private function resolvePrefixedHub(string $hubRoute, string $route, array $detailParents): ?array
    {
        $prefix = $hubRoute . '_';

        if (preg_match('/^' . preg_quote($prefix, '/') . '(.+?)_(show|nova|editar|form)$/', $route, $m)) {
            $parent = $detailParents[$m[1]] ?? null;
            if ($parent !== null) {
                return ['route' => $parent, 'params' => []];
            }
        }

        if ($route !== $hubRoute) {
            return ['route' => $hubRoute, 'params' => []];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $routeParams
     *
     * @return array<string, mixed>
     */
    private function inheritRouteParams(string $parentRoute, array $routeParams): array
    {
        if (!str_ends_with($parentRoute, '_show')
            && !str_ends_with($parentRoute, '_detalhe')
            && !str_ends_with($parentRoute, '_ficha')) {
            return [];
        }

        $inherited = [];
        foreach (['id', 'slug', 'uuid'] as $key) {
            if (array_key_exists($key, $routeParams)) {
                $inherited[$key] = $routeParams[$key];
            }
        }

        return $inherited;
    }

    private function normalizeRoute(string $route): string
    {
        if ($route === 'app_dashboard') {
            return $this->organismoRedirects->homeRoute();
        }

        return $route;
    }
}
