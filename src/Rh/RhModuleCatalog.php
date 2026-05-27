<?php

namespace App\Rh;

/**
 * Catálogo dos módulos RH (roadmap completo).
 */
final class RhModuleCatalog
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            ['id' => 'funcionarios', 'grant' => 'funcionarios', 'route' => 'app_rh_funcionarios', 'icon' => 'fa-users', 'title' => 'Funcionários', 'subtitle' => 'Cadastro e fichas', 'tone' => 'blue', 'phase' => 1],
            ['id' => 'admissoes', 'grant' => 'admissoes', 'route' => 'app_rh_admissoes', 'icon' => 'fa-user-plus', 'title' => 'Admissões', 'subtitle' => 'Onboarding', 'tone' => 'green', 'phase' => 1],
            ['id' => 'demissoes', 'grant' => 'admissoes', 'route' => 'app_rh_demissoes', 'icon' => 'fa-user-minus', 'title' => 'Demissões', 'subtitle' => 'Offboarding', 'tone' => 'rose', 'phase' => 1],
            ['id' => 'ferias', 'grant' => 'ferias', 'route' => 'app_rh_ferias', 'icon' => 'fa-umbrella-beach', 'title' => 'Férias', 'subtitle' => 'Solicitações e aprovações', 'tone' => 'amber', 'phase' => 1],
            ['id' => 'folha', 'grant' => 'folha', 'route' => 'app_rh_folha', 'icon' => 'fa-file-invoice-dollar', 'title' => 'Folha', 'subtitle' => 'Competências operacionais', 'tone' => 'teal', 'phase' => 1],
            ['id' => 'portal', 'grant' => 'portal', 'route' => 'app_rh_portal', 'icon' => 'fa-id-badge', 'title' => 'Portal do colaborador', 'subtitle' => 'Holerite, férias, dados', 'tone' => 'violet', 'phase' => 1],
            ['id' => 'recrutamento', 'grant' => 'recrutamento', 'route' => 'app_rh_recrutamento', 'icon' => 'fa-briefcase', 'title' => 'Recrutamento', 'subtitle' => 'Vagas e candidatos', 'tone' => 'blue', 'phase' => 1],
            ['id' => 'ponto', 'grant' => 'ponto', 'route' => 'app_rh_ponto', 'icon' => 'fa-clock', 'title' => 'Ponto', 'subtitle' => 'Batidas e espelho', 'tone' => 'amber', 'phase' => 1],
            ['id' => 'comunicacao', 'grant' => 'comunicacao', 'route' => 'app_rh_comunicacao', 'icon' => 'fa-envelope', 'title' => 'Comunicação', 'subtitle' => 'Comunicados e e-mails', 'tone' => 'teal', 'phase' => 1],
            ['id' => 'organograma', 'grant' => 'organograma', 'route' => 'app_rh_organograma', 'icon' => 'fa-sitemap', 'title' => 'Organograma', 'subtitle' => 'Hierarquia da equipe', 'tone' => 'green', 'phase' => 1],
            ['id' => 'auditoria', 'grant' => 'auditoria', 'route' => 'app_rh_auditoria', 'icon' => 'fa-shield-halved', 'title' => 'Auditoria', 'subtitle' => 'Trilha de ações', 'tone' => 'rose', 'phase' => 1],
            ['id' => 'workflows', 'grant' => 'workflows', 'route' => 'app_rh_workflows', 'icon' => 'fa-diagram-project', 'title' => 'Workflows', 'subtitle' => 'Checklists configuráveis', 'tone' => 'violet', 'phase' => 1],
            ['id' => 'folha_legal', 'grant' => 'folha_legal', 'route' => 'app_rh_folha_legal', 'icon' => 'fa-scale-balanced', 'title' => 'Folha legal', 'subtitle' => 'INSS, IRRF, FGTS, holerite', 'tone' => 'blue', 'phase' => 2],
            ['id' => 'contabilidade', 'grant' => 'contabilidade', 'route' => 'app_rh_contabilidade', 'icon' => 'fa-calculator', 'title' => 'Provisões', 'subtitle' => 'Férias, 13º e encargos', 'tone' => 'teal', 'phase' => 2],
            ['id' => 'esocial', 'grant' => 'esocial', 'route' => 'app_rh_esocial', 'icon' => 'fa-landmark', 'title' => 'eSocial', 'subtitle' => 'Lotes e eventos', 'tone' => 'amber', 'phase' => 2],
            ['id' => 'assinatura', 'grant' => 'assinatura', 'route' => 'app_rh_assinatura', 'icon' => 'fa-file-signature', 'title' => 'Assinatura digital', 'subtitle' => 'Contratos e termos', 'tone' => 'green', 'phase' => 2],
            ['id' => 'analytics', 'grant' => 'analytics', 'route' => 'app_rh_analytics', 'icon' => 'fa-chart-line', 'title' => 'Analytics RH', 'subtitle' => 'BI e indicadores', 'tone' => 'violet', 'phase' => 3],
        ];
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $mod) {
            if ($mod['id'] === $id) {
                return $mod;
            }
        }

        return null;
    }
}
