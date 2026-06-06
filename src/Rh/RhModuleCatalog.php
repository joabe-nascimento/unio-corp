<?php



namespace App\Rh;



/**

 * Catálogo dos módulos RH (roadmap completo).

 */

final class RhModuleCatalog

{

    public const GROUP_OPERACAO = 'operacao';

    public const GROUP_FOLHA = 'folha';



    /** @deprecated Use GROUP_OPERACAO */

    public const GROUP_EQUIPE = self::GROUP_OPERACAO;

    /** @deprecated Use GROUP_OPERACAO */

    public const GROUP_GESTAO = self::GROUP_OPERACAO;

    /** @deprecated Use GROUP_FOLHA */

    public const GROUP_DADOS = self::GROUP_FOLHA;



    /** @return array<string, string> */

    public static function groupLabels(): array

    {

        return [

            self::GROUP_OPERACAO => 'Operação',

            self::GROUP_FOLHA => 'Folha & compliance',

        ];

    }



    /** @return list<array<string, mixed>> */

    public static function all(): array

    {

        return [

            ['id' => 'funcionarios', 'grant' => 'funcionarios', 'route' => 'app_rh_funcionarios', 'icon' => 'fa-users', 'title' => 'Funcionários', 'short' => 'Funcionários', 'subtitle' => 'Cadastro e fichas', 'tone' => 'blue', 'phase' => 1, 'group' => self::GROUP_OPERACAO],

            ['id' => 'admissoes', 'grant' => 'admissoes', 'route' => 'app_rh_admissoes', 'icon' => 'fa-user-plus', 'title' => 'Admissões', 'short' => 'Admissões', 'subtitle' => 'Onboarding', 'tone' => 'green', 'phase' => 1, 'group' => self::GROUP_OPERACAO, 'activity_pulse' => true],

            ['id' => 'demissoes', 'grant' => 'admissoes', 'route' => 'app_rh_demissoes', 'icon' => 'fa-user-minus', 'title' => 'Demissões', 'short' => 'Demissões', 'subtitle' => 'Offboarding', 'tone' => 'rose', 'phase' => 1, 'group' => self::GROUP_OPERACAO, 'activity_pulse' => true],

            ['id' => 'ferias', 'grant' => 'ferias', 'route' => 'app_rh_ferias', 'icon' => 'fa-umbrella-beach', 'title' => 'Férias', 'short' => 'Férias', 'subtitle' => 'Solicitações e aprovações', 'tone' => 'amber', 'phase' => 1, 'group' => self::GROUP_OPERACAO, 'activity_pulse' => true],

            ['id' => 'portal', 'grant' => 'portal', 'route' => 'app_rh_portal', 'icon' => 'fa-id-badge', 'title' => 'Portal do colaborador', 'short' => 'Portal', 'subtitle' => 'Holerite, férias, dados', 'tone' => 'violet', 'phase' => 1, 'group' => self::GROUP_OPERACAO],

            ['id' => 'organograma', 'grant' => 'organograma', 'route' => 'app_rh_organograma', 'icon' => 'fa-sitemap', 'title' => 'Organograma', 'short' => 'Organograma', 'subtitle' => 'Hierarquia da equipe', 'tone' => 'green', 'phase' => 1, 'group' => self::GROUP_OPERACAO],

            ['id' => 'recrutamento', 'grant' => 'recrutamento', 'route' => 'app_recrutamento_vagas', 'icon' => 'fa-briefcase', 'title' => 'Recrutamento', 'short' => 'Vagas', 'subtitle' => 'Vagas e candidatos', 'tone' => 'blue', 'phase' => 1, 'group' => self::GROUP_OPERACAO, 'activity_pulse' => true],

            ['id' => 'ponto', 'grant' => 'ponto', 'route' => 'app_rh_ponto', 'icon' => 'fa-clock', 'title' => 'Ponto', 'short' => 'Ponto', 'subtitle' => 'Batidas e espelho', 'tone' => 'amber', 'phase' => 1, 'group' => self::GROUP_OPERACAO],

            ['id' => 'comunicacao', 'grant' => 'comunicacao', 'route' => 'app_rh_comunicacao', 'icon' => 'fa-envelope', 'title' => 'Comunicação', 'short' => 'Comunicados', 'subtitle' => 'Comunicados e e-mails', 'tone' => 'teal', 'phase' => 1, 'group' => self::GROUP_OPERACAO],

            ['id' => 'workflows', 'grant' => 'workflows', 'route' => 'app_rh_workflows', 'icon' => 'fa-diagram-project', 'title' => 'Workflows', 'short' => 'Workflows', 'subtitle' => 'Checklists configuráveis', 'tone' => 'violet', 'phase' => 1, 'group' => self::GROUP_OPERACAO],

            ['id' => 'auditoria', 'grant' => 'auditoria', 'route' => 'app_rh_auditoria', 'icon' => 'fa-shield-halved', 'title' => 'Auditoria', 'short' => 'Auditoria', 'subtitle' => 'Trilha de ações', 'tone' => 'rose', 'phase' => 1, 'group' => self::GROUP_OPERACAO],

            ['id' => 'folha', 'grant' => 'folha', 'route' => 'app_rh_folha', 'icon' => 'fa-file-invoice-dollar', 'title' => 'Folha', 'short' => 'Folha', 'sidebar_label' => 'Folha de pag.', 'subtitle' => 'Competências operacionais', 'tone' => 'teal', 'phase' => 1, 'group' => self::GROUP_FOLHA],

            ['id' => 'folha_legal', 'grant' => 'folha_legal', 'route' => 'app_rh_folha_legal', 'icon' => 'fa-scale-balanced', 'title' => 'Folha legal', 'short' => 'Folha legal', 'subtitle' => 'INSS, IRRF, FGTS, holerite', 'tone' => 'blue', 'phase' => 2, 'group' => self::GROUP_FOLHA],

            ['id' => 'contabilidade', 'grant' => 'contabilidade', 'route' => 'app_rh_contabilidade', 'icon' => 'fa-calculator', 'title' => 'Provisões', 'short' => 'Provisões', 'subtitle' => 'Férias, 13º e encargos', 'tone' => 'teal', 'phase' => 2, 'group' => self::GROUP_FOLHA],

            ['id' => 'esocial', 'grant' => 'esocial', 'route' => 'app_rh_esocial', 'icon' => 'fa-landmark', 'title' => 'eSocial', 'short' => 'eSocial', 'subtitle' => 'Lotes e eventos', 'tone' => 'amber', 'phase' => 2, 'group' => self::GROUP_FOLHA, 'activity_pulse' => true],

            ['id' => 'assinatura', 'grant' => 'assinatura', 'route' => 'app_rh_assinatura', 'icon' => 'fa-file-signature', 'title' => 'Assinatura digital', 'short' => 'Assinatura', 'subtitle' => 'Contratos e termos', 'tone' => 'green', 'phase' => 2, 'group' => self::GROUP_FOLHA],

            ['id' => 'analytics', 'grant' => 'analytics', 'route' => 'app_rh_analytics', 'icon' => 'fa-chart-line', 'title' => 'Analytics RH', 'short' => 'Analytics', 'subtitle' => 'BI e indicadores', 'tone' => 'violet', 'phase' => 3, 'group' => self::GROUP_FOLHA],

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

    public static function sidebarLabel(array $mod): string
    {
        return (string) ($mod['sidebar_label'] ?? $mod['short'] ?? $mod['title'] ?? '');
    }

    public static function isRouteActive(?string $currentRoute, string $moduleRoute): bool
    {
        if ($currentRoute === null || $currentRoute === '') {
            return false;
        }

        if ($moduleRoute === 'app_rh') {
            return $currentRoute === 'app_rh';
        }

        if ($moduleRoute === 'app_recrutamento_vagas') {
            return str_starts_with($currentRoute, 'app_recrutamento')
                || str_starts_with($currentRoute, 'app_rh_recrutamento');
        }

        return str_starts_with($currentRoute, $moduleRoute);
    }

}

