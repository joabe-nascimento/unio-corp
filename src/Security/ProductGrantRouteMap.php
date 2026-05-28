<?php

namespace App\Security;

/**
 * Mapeamento rota Symfony → escopo + produto (matriz de permissões).
 *
 * @return array<string, array{scope: string, product: string}>
 */
final class ProductGrantRouteMap
{
    public const MAP = [
        // Hub Operações
        'app_hub_operacoes' => ['scope' => 'hub_operacoes', 'product' => 'rh'],

        // RH
        'app_rh' => ['scope' => 'hub_operacoes', 'product' => 'rh'],
        'app_rh_funcionarios' => ['scope' => 'product_rh', 'product' => 'funcionarios'],
        'app_rh_funcionario_novo' => ['scope' => 'product_rh', 'product' => 'funcionarios'],
        'app_rh_funcionario_show' => ['scope' => 'product_rh', 'product' => 'funcionarios'],
        'app_rh_funcionario_editar' => ['scope' => 'product_rh', 'product' => 'funcionarios'],
        'app_rh_admissoes' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_admissoes_nova' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_admissoes_show' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_demissoes' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_demissoes_nova' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_demissoes_show' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_ferias' => ['scope' => 'product_rh', 'product' => 'ferias'],
        'app_rh_ferias_nova' => ['scope' => 'product_rh', 'product' => 'ferias'],
        'app_rh_ferias_show' => ['scope' => 'product_rh', 'product' => 'ferias'],
        'app_rh_folha' => ['scope' => 'product_rh', 'product' => 'folha'],
        'app_rh_folha_gerar' => ['scope' => 'product_rh', 'product' => 'folha'],
        'app_rh_folha_show' => ['scope' => 'product_rh', 'product' => 'folha'],
        'app_rh_folha_export' => ['scope' => 'product_rh', 'product' => 'folha'],
        'app_rh_portal' => ['scope' => 'product_rh', 'product' => 'portal'],
        'app_rh_portal_ferias' => ['scope' => 'product_rh', 'product' => 'portal'],
        'app_rh_portal_holerites' => ['scope' => 'product_rh', 'product' => 'portal'],
        'app_rh_portal_holerite' => ['scope' => 'product_rh', 'product' => 'portal'],
        'app_rh_portal_comunicados' => ['scope' => 'product_rh', 'product' => 'portal'],
        'app_rh_recrutamento' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_rh_recrutamento_candidato' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_rh_ponto' => ['scope' => 'product_rh', 'product' => 'ponto'],
        'app_rh_comunicacao' => ['scope' => 'product_rh', 'product' => 'comunicacao'],
        'app_rh_organograma' => ['scope' => 'product_rh', 'product' => 'organograma'],
        'app_rh_auditoria' => ['scope' => 'product_rh', 'product' => 'auditoria'],
        'app_rh_workflows' => ['scope' => 'product_rh', 'product' => 'workflows'],
        'app_rh_folha_legal' => ['scope' => 'product_rh', 'product' => 'folha_legal'],
        'app_rh_contabilidade' => ['scope' => 'product_rh', 'product' => 'contabilidade'],
        'app_rh_esocial' => ['scope' => 'product_rh', 'product' => 'esocial'],
        'app_rh_assinatura' => ['scope' => 'product_rh', 'product' => 'assinatura'],
        'app_rh_analytics' => ['scope' => 'product_rh', 'product' => 'analytics'],

        // Pessoas
        'app_pessoas' => ['scope' => 'hub_operacoes', 'product' => 'pessoas'],
        'app_pessoas_membros' => ['scope' => 'product_pessoas', 'product' => 'membros'],
        'app_pessoas_membro_novo' => ['scope' => 'product_pessoas', 'product' => 'membros'],
        'app_pessoas_membro_ficha' => ['scope' => 'product_pessoas', 'product' => 'membros'],
        'app_pessoas_equipes' => ['scope' => 'product_pessoas', 'product' => 'equipes'],
        'app_pessoas_equipe_nova' => ['scope' => 'product_pessoas', 'product' => 'equipes'],
        'app_pessoas_equipe_detalhe' => ['scope' => 'product_pessoas', 'product' => 'equipes'],
        'app_pessoas_cargos' => ['scope' => 'product_pessoas', 'product' => 'cargos'],
        'app_pessoas_organograma' => ['scope' => 'product_pessoas', 'product' => 'membros'],
        'app_pessoas_avaliacao' => ['scope' => 'product_pessoas', 'product' => 'avaliacao'],

        // Engenharia
        'app_engenharia' => ['scope' => 'hub_operacoes', 'product' => 'engenharia'],
        'app_engenharia_projetos' => ['scope' => 'product_engenharia', 'product' => 'projetos'],
        'app_engenharia_cronograma' => ['scope' => 'product_engenharia', 'product' => 'cronograma'],
        'app_engenharia_orcamentos' => ['scope' => 'product_engenharia', 'product' => 'orcamentos'],
        'app_engenharia_equipes' => ['scope' => 'product_engenharia', 'product' => 'equipes'],
        'app_engenharia_documentacao' => ['scope' => 'product_engenharia', 'product' => 'projetos'],
        'app_engenharia_normas' => ['scope' => 'product_engenharia', 'product' => 'projetos'],

        // Talentos
        'app_talentos' => ['scope' => 'hub_talentos', 'product' => 'banco'],
        'app_talentos_banco' => ['scope' => 'hub_talentos', 'product' => 'banco'],
        'app_talentos_vagas' => ['scope' => 'hub_talentos', 'product' => 'vagas'],
        'app_talentos_trilhas' => ['scope' => 'hub_talentos', 'product' => 'trilhas'],
        'app_talentos_mentoria' => ['scope' => 'hub_talentos', 'product' => 'mentorias'],

        // Maturidade
        'app_maturidade' => ['scope' => 'hub_maturidade', 'product' => 'avaliacao'],
        'app_maturidade_avaliacao' => ['scope' => 'hub_maturidade', 'product' => 'avaliacao'],
        'app_maturidade_radar' => ['scope' => 'hub_maturidade', 'product' => 'radar'],
        'app_maturidade_plano' => ['scope' => 'hub_maturidade', 'product' => 'plano'],
        'app_maturidade_historico' => ['scope' => 'hub_maturidade', 'product' => 'historico'],

        // Publicidade
        'app_publicidade' => ['scope' => 'product_publicidade', 'product' => 'campanhas'],
        'app_publicidade_campanhas' => ['scope' => 'product_publicidade', 'product' => 'campanhas'],
        'app_publicidade_clientes' => ['scope' => 'product_publicidade', 'product' => 'clientes'],
        'app_publicidade_criativos' => ['scope' => 'product_publicidade', 'product' => 'criativos'],
        'app_publicidade_midia' => ['scope' => 'product_publicidade', 'product' => 'campanhas'],
        'app_publicidade_briefings' => ['scope' => 'product_publicidade', 'product' => 'campanhas'],
        'app_publicidade_metricas' => ['scope' => 'product_publicidade', 'product' => 'metricas'],

        // Core — Projetos e Metas (desenvolvimento Unio)
        'app_core_projetos' => ['scope' => 'product_core', 'product' => 'projetos'],
        'app_core_projetos_nova' => ['scope' => 'product_core', 'product' => 'projetos'],
        'app_core_projetos_show' => ['scope' => 'product_core', 'product' => 'projetos'],
        'app_core_metas_nova' => ['scope' => 'product_core', 'product' => 'metas'],
        'app_core_tarefa_mover' => ['scope' => 'product_core', 'product' => 'projetos'],
        'app_core_tarefa_nova' => ['scope' => 'product_core', 'product' => 'projetos'],
        'app_core_tarefa_editar' => ['scope' => 'product_core', 'product' => 'projetos'],
        'app_core_tarefa_excluir' => ['scope' => 'product_core', 'product' => 'projetos'],

        // Admin (plataforma)
        'app_admin' => ['scope' => 'hub_admin', 'product' => 'usuarios'],
        'app_admin_usuarios' => ['scope' => 'hub_admin', 'product' => 'usuarios'],
        'app_admin_empresas' => ['scope' => 'hub_admin', 'product' => 'empresas'],
        'app_admin_configuracoes' => ['scope' => 'hub_admin', 'product' => 'configuracoes'],

        // Hubs em desenvolvimento (sem produtos)
        'app_comercial' => ['scope' => 'hub_comercial', 'product' => '_hub'],
        'app_beneficios' => ['scope' => 'hub_beneficios', 'product' => '_hub'],
        'app_academy' => ['scope' => 'hub_academy', 'product' => '_hub'],
        'app_parceiros' => ['scope' => 'hub_parceiros', 'product' => '_hub'],
        'app_financeiro' => ['scope' => 'hub_financeiro', 'product' => '_hub'],
        'app_compliance' => ['scope' => 'hub_compliance', 'product' => '_hub'],
        'app_analytics' => ['scope' => 'hub_analytics', 'product' => '_hub'],
        'app_juridico' => ['scope' => 'hub_juridico', 'product' => '_hub'],
        'app_clima' => ['scope' => 'hub_clima', 'product' => '_hub'],
        'app_sst' => ['scope' => 'hub_sst', 'product' => '_hub'],
        'app_comunicacao' => ['scope' => 'hub_comunicacao', 'product' => '_hub'],
        'app_hub_portal' => ['scope' => 'hub_portal', 'product' => '_hub'],
        'app_hub_recrutamento' => ['scope' => 'hub_recrutamento', 'product' => '_hub'],
        'app_esg' => ['scope' => 'hub_esg', 'product' => '_hub'],
        'app_suprimentos' => ['scope' => 'hub_suprimentos', 'product' => '_hub'],
        'app_ti' => ['scope' => 'hub_ti', 'product' => '_hub'],
        'app_expansao' => ['scope' => 'hub_expansao', 'product' => '_hub'],
        'app_qualidade' => ['scope' => 'hub_qualidade', 'product' => '_hub'],
        'app_facilities' => ['scope' => 'hub_facilities', 'product' => '_hub'],
        'app_patrimonio' => ['scope' => 'hub_patrimonio', 'product' => '_hub'],
        'app_conhecimento' => ['scope' => 'hub_conhecimento', 'product' => '_hub'],
        'app_integracoes' => ['scope' => 'hub_integracoes', 'product' => '_hub'],
        'app_customer_success' => ['scope' => 'hub_customer_success', 'product' => '_hub'],
        'app_inovacao' => ['scope' => 'hub_inovacao', 'product' => '_hub'],
        'app_holdings' => ['scope' => 'hub_holdings', 'product' => '_hub'],
        'app_seguros' => ['scope' => 'hub_seguros', 'product' => '_hub'],
        'app_saude_ocupacional' => ['scope' => 'hub_saude_ocupacional', 'product' => '_hub'],
        'app_licitacoes' => ['scope' => 'hub_licitacoes', 'product' => '_hub'],
        'app_marketing' => ['scope' => 'hub_marketing', 'product' => '_hub'],
        'app_lakehouse' => ['scope' => 'hub_lakehouse', 'product' => '_hub'],
        'app_franquias' => ['scope' => 'hub_franquias', 'product' => '_hub'],
        'app_seguranca_info' => ['scope' => 'hub_seguranca_info', 'product' => '_hub'],
        'app_pmo' => ['scope' => 'hub_pmo', 'product' => '_hub'],
        'app_treinamento_regulatorio' => ['scope' => 'hub_treinamento_regulatorio', 'product' => '_hub'],
        'app_terceiros' => ['scope' => 'hub_terceiros', 'product' => '_hub'],
    ];
}
