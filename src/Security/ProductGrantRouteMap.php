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
        'app_rh_admissoes' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_admissoes_nova' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_admissoes_show' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_demissoes' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_demissoes_nova' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_demissoes_show' => ['scope' => 'product_rh', 'product' => 'admissoes'],
        'app_rh_ferias' => ['scope' => 'product_rh', 'product' => 'ferias'],
        'app_rh_folha' => ['scope' => 'product_rh', 'product' => 'folha'],

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

        // Admin (plataforma)
        'app_admin' => ['scope' => 'hub_admin', 'product' => 'usuarios'],
        'app_admin_usuarios' => ['scope' => 'hub_admin', 'product' => 'usuarios'],
        'app_admin_empresas' => ['scope' => 'hub_admin', 'product' => 'empresas'],
        'app_admin_configuracoes' => ['scope' => 'hub_admin', 'product' => 'configuracoes'],
    ];
}
