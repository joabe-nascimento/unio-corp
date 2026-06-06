<?php

namespace App\Service;

/**
 * Resolve a rota "voltar" para telas filhas de um hub (RH, Pessoas, Engenharia, etc.).
 */
final class PageBackResolver
{
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
        'app_welcome_news_show' => 'app_welcome',
        'app_cortex' => 'app_dashboard',
    ];

    /** segmento da rota de detalhe => rota da listagem */
    private const RH_LIST_PARENT = [
        'funcionario' => 'app_rh_funcionarios',
        'admissoes' => 'app_rh_admissoes',
        'demissoes' => 'app_rh_demissoes',
        'ferias' => 'app_rh_ferias',
        'folha' => 'app_rh_folha',
    ];

    /**
     * @return array{route: string, params: array<string, mixed>}|null
     */
    public function resolve(?string $currentRoute): ?array
    {
        if ($currentRoute === null || $currentRoute === '') {
            return null;
        }

        if (isset(self::HUB_PARENT[$currentRoute])) {
            return ['route' => self::HUB_PARENT[$currentRoute], 'params' => []];
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
}
