<?php

namespace App\Service;

use App\Dev\DevSeedEmails;
use App\Entity\Empresa;
use App\Entity\User;
use App\Entity\UserProductGrant;
use App\Repository\FuncionarioRepository;
use App\Repository\UserProductGrantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Escopos de permissùo por hub/produto.
 * Membros vùm do banco (empresa ativa); grants do banco com fallback em DEFAULT_GRANTS.
 * Tenant nùo aparece na matriz: acesso total implùcito.
 */
class PermissionService
{
    /** @var list<array{id: string, label: string, class: string, nivel: int, description: string}> */
    public const ASSIGNABLE_PROFILES = [
        ['id' => 'MEMBRO', 'label' => 'Membro', 'class' => 'membro', 'nivel' => 1, 'description' => 'Acesso de participaùùo: visualiza e usa o produto, sem gerenciar pessoas ou configuraùùes.'],
        ['id' => 'SUPERVISOR_EQUIPE', 'label' => 'Supervisor de Equipe', 'class' => 'supervisor-equipe', 'nivel' => 2, 'description' => 'Coordena a equipe no produto: acompanha entregas, aprova aùùes do time e orienta o dia a dia.'],
        ['id' => 'SUPERVISOR', 'label' => 'Supervisor Geral', 'class' => 'supervisor', 'nivel' => 3, 'description' => 'Supervisiona vùrias equipes ou frentes do hub, com visùo ampla de processos e indicadores.'],
        ['id' => 'GESTOR_EQUIPE', 'label' => 'Gestor de Equipe', 'class' => 'gestor-equipe', 'nivel' => 4, 'description' => 'Gerencia membros e permissùes da equipe nos produtos em que atua.'],
        ['id' => 'GESTOR', 'label' => 'Gestor', 'class' => 'gestor', 'nivel' => 5, 'description' => 'Controle amplo do produto ou mùdulo: configuraùùes, acessos e operaùùo completa da ùrea.'],
    ];

    /** Nùvel numùrico do perfil assignùvel (para comparar grants). */
    public static function profileNivel(string $profileId): int
    {
        foreach (self::ASSIGNABLE_PROFILES as $profile) {
            if ($profile['id'] === $profileId) {
                return $profile['nivel'];
            }
        }

        return 0;
    }

    public static function profileLabel(string $profileId): string
    {
        foreach (self::ASSIGNABLE_PROFILES as $profile) {
            if ($profile['id'] === $profileId) {
                return $profile['label'];
            }
        }

        return $profileId;
    }

    public static function profileClass(string $profileId): string
    {
        foreach (self::ASSIGNABLE_PROFILES as $profile) {
            if ($profile['id'] === $profileId) {
                return $profile['class'];
            }
        }

        return 'default';
    }

    /** Painel/aba Permissùes ù perfil global ou grant ? Gestor de Equipe no escopo. */
    public function canManagePermissions(User $user, ?string $scope = null): bool
    {
        if ($user->hasPlatformAccess()) {
            return true;
        }

        if (\in_array($user->getPerfil(), ['GESTOR', 'GESTOR_EQUIPE'], true)) {
            return true;
        }

        if ($scope !== null) {
            return $this->userHasManageGrantInScope($user, $scope);
        }

        foreach (array_keys(self::SCOPES) as $scopeId) {
            if ($this->userHasManageGrantInScope($user, $scopeId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Editor com grant granular sù pode salvar escopos em que ù gestor.
     *
     * @param array<string, string> $grantsMap keys "scope:productId" => perfil_id
     */
    public function canEditorSaveGrants(User $editor, array $grantsMap): bool
    {
        if ($this->canManagePermissions($editor)) {
            if ($editor->hasPlatformAccess() || \in_array($editor->getPerfil(), ['GESTOR', 'GESTOR_EQUIPE'], true)) {
                return true;
            }
        } else {
            return false;
        }

        $scopesTouched = [];
        foreach ($grantsMap as $key => $perfilGrant) {
            if (!\is_string($key) || !str_contains($key, ':')) {
                continue;
            }
            [$scope] = explode(':', $key, 2);
            $scopesTouched[$scope] = true;
        }

        foreach (array_keys($scopesTouched) as $scope) {
            if (!$this->userHasManageGrantInScope($editor, $scope)) {
                return false;
            }
        }

        return $scopesTouched !== [];
    }

    private function userHasManageGrantInScope(User $user, string $scope): bool
    {
        if (!isset(self::SCOPES[$scope])) {
            return false;
        }

        $minLevel = self::profileNivel('GESTOR_EQUIPE');
        foreach (self::SCOPES[$scope]['products'] as $product) {
            $grant = $this->grantRepo->findOneForUserScopeProduct($user, $scope, $product['id']);
            if ($grant && $grant->getPerfilGrant() !== '' && self::profileNivel($grant->getPerfilGrant()) >= $minLevel) {
                return true;
            }
        }

        return false;
    }

    private const NO_ACCESS_DESCRIPTION = 'Sem permissùo neste produto ou hub ù o membro nùo consegue acessar a ùrea.';

    /** @var array<string, array{equipe: string, cargo: string}> */
    private const MEMBER_META = [
        'gestor@unio.dev' => ['equipe' => 'PMO', 'cargo' => 'Gestor de Operaùùes'],
        'gestor.eq@unio.dev' => ['equipe' => 'Squad Backend', 'cargo' => 'Gestor de Equipe'],
        'supervisor@unio.dev' => ['equipe' => 'ù', 'cargo' => 'Supervisor Geral'],
        'sup.eq@unio.dev' => ['equipe' => 'Obras e Projetos', 'cargo' => 'Supervisor de Campo'],
        'membro@unio.dev' => ['equipe' => 'Design & Marca', 'cargo' => 'Analista'],
    ];

    /**
     * Membros ilustrativos quando a empresa ativa nùo tem usuùrios no banco (preview de hubs).
     *
     * @var array<string, list<array{id: string, nome: string, email: string, perfil: string, equipe: string, cargo: string}>>
     */
    private const SCOPE_MOCK_MEMBERS = [
        'hub_pos_operatorio' => [
            [
                'id' => 'gestor',
                'nome' => 'Dr. Renato Almeida',
                'email' => 'gestor@unio.dev',
                'perfil' => 'GESTOR',
                'equipe' => 'Coordenaùùo Pùs-Op',
                'cargo' => 'Coordenador clùnico',
            ],
            [
                'id' => 'gestor-eq',
                'nome' => 'Enf. Camila Ribeiro',
                'email' => 'gestor.eq@unio.dev',
                'perfil' => 'GESTOR_EQUIPE',
                'equipe' => 'Enfermagem clùnica',
                'cargo' => 'Enfermeira responsùvel',
            ],
            [
                'id' => 'supervisor',
                'nome' => 'Dr. Paulo Menezes',
                'email' => 'supervisor@unio.dev',
                'perfil' => 'SUPERVISOR',
                'equipe' => 'Cirurgia geral',
                'cargo' => 'Mùdico supervisor',
            ],
            [
                'id' => 'sup-eq',
                'nome' => 'Enf. Lucas Ferreira',
                'email' => 'sup.eq@unio.dev',
                'perfil' => 'SUPERVISOR_EQUIPE',
                'equipe' => 'Plantùo noturno',
                'cargo' => 'Supervisor de plantùo',
            ],
            [
                'id' => 'membro',
                'nome' => 'Ana Beatriz Santos',
                'email' => 'membro@unio.dev',
                'perfil' => 'MEMBRO',
                'equipe' => 'Acompanhamento',
                'cargo' => 'Assistente de pùs-operatùrio',
            ],
        ],
    ];

    /** @var array<string, array{label: string, subtitle: string, products: list<array{id: string, label: string}>}> */
    public const SCOPES = [
        'hub_operacoes' => [
            'label' => 'Nùcleo de Operaùùes',
            'subtitle' => 'Permissùes por produto deste hub',
            'products' => [
                ['id' => 'rh', 'label' => 'Recursos Humanos'],
                ['id' => 'pessoas', 'label' => 'Gestùo de Pessoas'],
                ['id' => 'engenharia', 'label' => 'Obras e Projetos'],
            ],
        ],
        'hub_talentos' => [
            'label' => 'Nùcleo de Talentos',
            'subtitle' => 'Permissùes por produto deste hub',
            'products' => [
                ['id' => 'banco', 'label' => 'Banco de Talentos'],
                ['id' => 'vagas', 'label' => 'Vagas'],
                ['id' => 'trilhas', 'label' => 'Trilhas de Carreira'],
                ['id' => 'mentorias', 'label' => 'Mentorias'],
            ],
        ],
        'hub_maturidade' => [
            'label' => 'Nùcleo de Maturidade',
            'subtitle' => 'Permissùes por produto deste hub',
            'products' => [
                ['id' => 'avaliacao', 'label' => 'Avaliaùùo'],
                ['id' => 'plano', 'label' => 'Plano de Aùùo'],
                ['id' => 'historico', 'label' => 'Histùrico'],
                ['id' => 'radar', 'label' => 'Radar'],
            ],
        ],
        'hub_admin' => [
            'label' => 'Administraùùo',
            'subtitle' => 'Permissùes da plataforma',
            'products' => [
                ['id' => 'usuarios', 'label' => 'Usuùrios'],
                ['id' => 'empresas', 'label' => 'Empresas'],
                ['id' => 'configuracoes', 'label' => 'Configuraùùes'],
            ],
        ],
        'hub_comercial' => [
            'label' => 'Nùcleo Comercial',
            'subtitle' => 'CRM e pipeline comercial',
            'products' => [],
        ],
        'hub_beneficios' => [
            'label' => 'Nùcleo Benefùcios',
            'subtitle' => 'Marketplace de benefùcios',
            'products' => [],
        ],
        'hub_academy' => [
            'label' => 'Nùcleo Academy',
            'subtitle' => 'Educaùùo e trilhas de aprendizado',
            'products' => [],
        ],
        'hub_parceiros' => [
            'label' => 'Nùcleo Parceiros',
            'subtitle' => 'Rede de parceiros e revenda',
            'products' => [],
        ],
        'hub_financeiro' => [
            'label' => 'Nùcleo Financeiro',
            'subtitle' => 'Tesouraria e orùamento de pessoal',
            'products' => [],
        ],
        'hub_compliance' => [
            'label' => 'Nùcleo Compliance',
            'subtitle' => 'Normas, LGPD e auditorias',
            'products' => [],
        ],
        'hub_analytics' => [
            'label' => 'Nùcleo Analytics',
            'subtitle' => 'BI e indicadores',
            'products' => [],
        ],
        'hub_juridico' => [
            'label' => 'Nùcleo Jurùdico',
            'subtitle' => 'Trabalhista e contratos',
            'products' => [],
        ],
        'hub_clima' => [
            'label' => 'Nùcleo Clima',
            'subtitle' => 'Engajamento e eNPS',
            'products' => [],
        ],
        'hub_sst' => [
            'label' => 'Nùcleo SST',
            'subtitle' => 'Saùde e seguranùa do trabalho',
            'products' => [],
        ],
        'hub_comunicacao' => [
            'label' => 'Nùcleo Comunicaùùo',
            'subtitle' => 'Mural e cultura interna',
            'products' => [],
        ],
        'hub_publicidade' => [
            'label' => 'Nùcleo Publicidade',
            'subtitle' => 'Marca, campanhas e criativos',
            'products' => [
                ['id' => 'campanhas', 'label' => 'Campanhas'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'criativos', 'label' => 'Criativos'],
                ['id' => 'metricas', 'label' => 'Mùtricas'],
            ],
        ],
        'hub_obras' => [
            'label' => 'Nùcleo Obras e Projetos',
            'subtitle' => 'Engenharia e execuùùo de obras',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos'],
                ['id' => 'cronograma', 'label' => 'Cronograma'],
                ['id' => 'orcamentos', 'label' => 'Orùamentos'],
                ['id' => 'equipes', 'label' => 'Equipes de Campo'],
            ],
        ],
        'hub_portal' => [
            'label' => 'Nùcleo Portal do Colaborador',
            'subtitle' => 'Autoserviùo do colaborador',
            'products' => [],
        ],
        'hub_recrutamento' => [
            'label' => 'Nùcleo Recrutamento',
            'subtitle' => 'Seleùùo e pipeline de talentos',
            'products' => [
                ['id' => 'vagas', 'label' => 'Vagas'],
                ['id' => 'pipeline', 'label' => 'Pipeline'],
            ],
        ],
        'hub_esg' => [
            'label' => 'Nùcleo ESG',
            'subtitle' => 'Sustentabilidade e impacto',
            'products' => [],
        ],
        'hub_suprimentos' => [
            'label' => 'Nùcleo Suprimentos',
            'subtitle' => 'Compras e estoque',
            'products' => [],
        ],
        'hub_ti' => [
            'label' => 'Nùcleo TI',
            'subtitle' => 'NOC Center ù Service desk',
            'products' => [
                ['id' => 'chamados', 'label' => 'Chamados'],
                ['id' => 'catalogo', 'label' => 'Catùlogo'],
                ['id' => 'kb', 'label' => 'Base de Conhecimento'],
                ['id' => 'problemas', 'label' => 'Problemas'],
                ['id' => 'meus_chamados', 'label' => 'Meus Chamados'],
                ['id' => 'sla', 'label' => 'SLA'],
                ['id' => 'manutencoes', 'label' => 'Manutenùùes'],
                ['id' => 'ativos', 'label' => 'Ativos'],
                ['id' => 'licencas', 'label' => 'Licenùas'],
                ['id' => 'integracoes', 'label' => 'Integraùùes'],
                ['id' => 'cortex', 'label' => 'Cortex Ops'],
                ['id' => 'analytics', 'label' => 'Analytics'],
                ['id' => 'novidades', 'label' => 'Novidades'],
            ],
        ],
        'hub_expansao' => [
            'label' => 'Nùcleo Expansùo',
            'subtitle' => 'Franquias e novos mercados',
            'products' => [],
        ],
        'hub_qualidade' => [
            'label' => 'Nùcleo Qualidade',
            'subtitle' => 'ISO, auditorias e nùo conformidades',
            'products' => [],
        ],
        'hub_facilities' => [
            'label' => 'Nùcleo Facilities',
            'subtitle' => 'Predial, frota e manutenùùo',
            'products' => [],
        ],
        'hub_patrimonio' => [
            'label' => 'Nùcleo Patrimùnio',
            'subtitle' => 'Inventùrio e ativos',
            'products' => [],
        ],
        'hub_conhecimento' => [
            'label' => 'Nùcleo Conhecimento',
            'subtitle' => 'Wiki, SOPs e playbooks',
            'products' => [],
        ],
        'hub_integracoes' => [
            'label' => 'Nùcleo Integraùùes',
            'subtitle' => 'APIs, conectores e webhooks',
            'products' => [
                ['id' => 'observatorio', 'label' => 'Observatùrio Causal'],
                ['id' => 'catalogo', 'label' => 'Catùlogo'],
                ['id' => 'conectores', 'label' => 'Conectores'],
                ['id' => 'webhooks', 'label' => 'Webhooks'],
                ['id' => 'mapeamentos', 'label' => 'Mapeamentos'],
                ['id' => 'api_keys', 'label' => 'API & chaves'],
                ['id' => 'logs', 'label' => 'Logs'],
                ['id' => 'playbooks', 'label' => 'Playbooks'],
            ],
        ],
        'hub_customer_success' => [
            'label' => 'Nùcleo Customer Success',
            'subtitle' => 'Pùs-venda e retenùùo',
            'products' => [],
        ],
        'hub_inovacao' => [
            'label' => 'Nùcleo Inovaùùo',
            'subtitle' => 'Labs e experimentos',
            'products' => [
                ['id' => 'pipeline', 'label' => 'Pipeline'],
                ['id' => 'laboratorio', 'label' => 'Laboratùrio'],
                ['id' => 'experimentos', 'label' => 'Experimentos'],
                ['id' => 'backlog', 'label' => 'Backlog'],
                ['id' => 'analytics', 'label' => 'Analytics'],
                ['id' => 'conexoes', 'label' => 'Conexùes'],
                ['id' => 'impact', 'label' => 'Impacto'],
                ['id' => 'tendencias', 'label' => 'Tendùncias'],
                ['id' => 'portfolio', 'label' => 'Portfùlio'],
                ['id' => 'novidades', 'label' => 'Novidades'],
            ],
        ],
        'hub_holdings' => [
            'label' => 'Nùcleo Multi-empresa',
            'subtitle' => 'Holdings e visùo consolidada',
            'products' => [],
        ],
        'hub_seguros' => [
            'label' => 'Nùcleo Seguros',
            'subtitle' => 'Seguros e benefùcios corporativos',
            'products' => [],
        ],
        'hub_saude_ocupacional' => [
            'label' => 'Nùcleo Saùde Ocupacional',
            'subtitle' => 'PCMSO, exames e medicina do trabalho',
            'products' => [
                ['id' => 'pcmso', 'label' => 'PCMSO'],
                ['id' => 'exames', 'label' => 'Exames ocupacionais'],
                ['id' => 'aso', 'label' => 'ASO'],
                ['id' => 'agendamentos', 'label' => 'Agendamentos'],
                ['id' => 'afastamentos', 'label' => 'Afastamentos'],
                ['id' => 'prontuario', 'label' => 'Prontuùrio ocupacional'],
            ],
        ],
        'hub_pos_operatorio' => [
            'label' => 'Unio Saùde',
            'subtitle' => 'Acompanhamento clùnico pùs-cirùrgico',
            'products' => [
                ['id' => 'pacientes', 'label' => 'Pacientes'],
                ['id' => 'protocolos', 'label' => 'Protocolos'],
                ['id' => 'questionarios', 'label' => 'Questionùrios'],
                ['id' => 'alertas', 'label' => 'Alertas clùnicos'],
                ['id' => 'painel', 'label' => 'Painel de recuperaùùo'],
                ['id' => 'portal_paciente', 'label' => 'Portal do paciente'],
            ],
        ],
        'hub_licitacoes' => [
            'label' => 'Nùcleo Licitaùùes',
            'subtitle' => 'Contratos pùblicos e B2G',
            'products' => [],
        ],
        'hub_marketing' => [
            'label' => 'Nùcleo Marketing',
            'subtitle' => 'Demand gen, leads e campanhas',
            'products' => [],
        ],
        'hub_lakehouse' => [
            'label' => 'Nùcleo Data & Lakehouse',
            'subtitle' => 'Dados brutos e pipelines',
            'products' => [],
        ],
        'hub_franquias' => [
            'label' => 'Nùcleo Franquias & Unidades',
            'subtitle' => 'Rede de unidades e franqueados',
            'products' => [],
        ],
        'hub_seguranca_info' => [
            'label' => 'Nùcleo Seguranùa da Informaùùo',
            'subtitle' => 'LGPD tùcnica e incidentes',
            'products' => [],
        ],
        'hub_pmo' => [
            'label' => 'Nùcleo PMO',
            'subtitle' => 'Projetos internos e governanùa',
            'products' => [],
        ],
        'hub_treinamento_regulatorio' => [
            'label' => 'Nùcleo Treinamento Regulatùrio',
            'subtitle' => 'NR, certificaùùes e obrigaùùes',
            'products' => [],
        ],
        'hub_terceiros' => [
            'label' => 'Nùcleo Gestùo de Terceiros',
            'subtitle' => 'PJ, fornecedores e mùo de obra',
            'products' => [],
        ],
        'product_rh' => [
            'label' => 'Recursos Humanos',
            'subtitle' => 'Permissùes por ùrea do mùdulo',
            'products' => [
                ['id' => 'funcionarios', 'label' => 'Funcionùrios'],
                ['id' => 'admissoes', 'label' => 'Admissùes'],
                ['id' => 'ferias', 'label' => 'Fùrias'],
                ['id' => 'folha', 'label' => 'Folha'],
                ['id' => 'portal', 'label' => 'Portal do colaborador'],
                ['id' => 'recrutamento', 'label' => 'Recrutamento'],
                ['id' => 'ponto', 'label' => 'Ponto'],
                ['id' => 'comunicacao', 'label' => 'Comunicaùùo'],
                ['id' => 'organograma', 'label' => 'Organograma'],
                ['id' => 'auditoria', 'label' => 'Auditoria'],
                ['id' => 'workflows', 'label' => 'Workflows'],
                ['id' => 'folha_legal', 'label' => 'Folha legal'],
                ['id' => 'contabilidade', 'label' => 'Provisùes'],
                ['id' => 'esocial', 'label' => 'eSocial'],
                ['id' => 'assinatura', 'label' => 'Assinatura digital'],
                ['id' => 'analytics', 'label' => 'Analytics RH'],
            ],
        ],
        'product_pessoas' => [
            'label' => 'Gestùo de Pessoas',
            'subtitle' => 'Permissùes por ùrea do mùdulo',
            'products' => [
                ['id' => 'membros', 'label' => 'Membros'],
                ['id' => 'equipes', 'label' => 'Equipes'],
                ['id' => 'cargos', 'label' => 'Cargos'],
                ['id' => 'avaliacao', 'label' => 'Avaliaùùo'],
            ],
        ],
        'product_engenharia' => [
            'label' => 'Obras e Projetos',
            'subtitle' => 'Permissùes por ùrea do mùdulo',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos'],
                ['id' => 'cronograma', 'label' => 'Cronograma'],
                ['id' => 'orcamentos', 'label' => 'Orùamentos'],
                ['id' => 'equipes', 'label' => 'Equipes de Campo'],
            ],
        ],
        'product_publicidade' => [
            'label' => 'Marca e Comunicaùùo',
            'subtitle' => 'Permissùes por ùrea do mùdulo',
            'products' => [
                ['id' => 'campanhas', 'label' => 'Campanhas'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'criativos', 'label' => 'Criativos'],
                ['id' => 'metricas', 'label' => 'Mùtricas'],
            ],
        ],
        'product_core' => [
            'label' => 'Projetos e Metas',
            'subtitle' => 'Quadro de desenvolvimento da plataforma Unio',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos e Kanban'],
                ['id' => 'metas', 'label' => 'Metas'],
            ],
        ],
    ];

    /** @var list<array{id: string, label: string, scope: string, products: list<array{id: string, label: string}>}> */
    public const ALL_HUB_GROUPS = [
        [
            'id' => 'hub_operacoes',
            'label' => 'Nùcleo de Operaùùes',
            'scope' => 'hub_operacoes',
            'products' => [
                ['id' => 'rh', 'label' => 'Recursos Humanos'],
                ['id' => 'pessoas', 'label' => 'Gestùo de Pessoas'],
                ['id' => 'engenharia', 'label' => 'Obras e Projetos'],
            ],
        ],
        [
            'id' => 'hub_talentos',
            'label' => 'Nùcleo de Talentos',
            'scope' => 'hub_talentos',
            'products' => [
                ['id' => 'banco', 'label' => 'Banco de Talentos'],
                ['id' => 'vagas', 'label' => 'Vagas'],
                ['id' => 'trilhas', 'label' => 'Trilhas de Carreira'],
                ['id' => 'mentorias', 'label' => 'Mentorias'],
            ],
        ],
        [
            'id' => 'hub_maturidade',
            'label' => 'Nùcleo de Maturidade',
            'scope' => 'hub_maturidade',
            'products' => [
                ['id' => 'avaliacao', 'label' => 'Avaliaùùo'],
                ['id' => 'plano', 'label' => 'Plano de Aùùo'],
                ['id' => 'historico', 'label' => 'Histùrico'],
                ['id' => 'radar', 'label' => 'Radar'],
            ],
        ],
        [
            'id' => 'hub_admin',
            'label' => 'Administraùùo',
            'scope' => 'hub_admin',
            'products' => [
                ['id' => 'usuarios', 'label' => 'Usuùrios'],
                ['id' => 'empresas', 'label' => 'Empresas'],
                ['id' => 'configuracoes', 'label' => 'Configuraùùes'],
            ],
        ],
        [
            'id' => 'hub_comercial',
            'label' => 'Nùcleo Comercial',
            'scope' => 'hub_comercial',
            'products' => [],
        ],
        [
            'id' => 'hub_beneficios',
            'label' => 'Nùcleo Benefùcios',
            'scope' => 'hub_beneficios',
            'products' => [],
        ],
        [
            'id' => 'hub_academy',
            'label' => 'Nùcleo Academy',
            'scope' => 'hub_academy',
            'products' => [],
        ],
        [
            'id' => 'hub_parceiros',
            'label' => 'Nùcleo Parceiros',
            'scope' => 'hub_parceiros',
            'products' => [],
        ],
        [
            'id' => 'hub_financeiro',
            'label' => 'Nùcleo Financeiro',
            'scope' => 'hub_financeiro',
            'products' => [],
        ],
        [
            'id' => 'hub_compliance',
            'label' => 'Nùcleo Compliance',
            'scope' => 'hub_compliance',
            'products' => [],
        ],
        [
            'id' => 'hub_analytics',
            'label' => 'Nùcleo Analytics',
            'scope' => 'hub_analytics',
            'products' => [],
        ],
        [
            'id' => 'hub_juridico',
            'label' => 'Nùcleo Jurùdico',
            'scope' => 'hub_juridico',
            'products' => [],
        ],
        [
            'id' => 'hub_clima',
            'label' => 'Nùcleo Clima',
            'scope' => 'hub_clima',
            'products' => [],
        ],
        [
            'id' => 'hub_sst',
            'label' => 'Nùcleo SST',
            'scope' => 'hub_sst',
            'products' => [],
        ],
        [
            'id' => 'hub_comunicacao',
            'label' => 'Nùcleo Comunicaùùo',
            'scope' => 'hub_comunicacao',
            'products' => [],
        ],
        [
            'id' => 'hub_publicidade',
            'label' => 'Nùcleo Publicidade',
            'scope' => 'hub_publicidade',
            'products' => [
                ['id' => 'campanhas', 'label' => 'Campanhas'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'criativos', 'label' => 'Criativos'],
                ['id' => 'metricas', 'label' => 'Mùtricas'],
            ],
        ],
        [
            'id' => 'hub_obras',
            'label' => 'Nùcleo Obras e Projetos',
            'scope' => 'hub_obras',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos'],
                ['id' => 'cronograma', 'label' => 'Cronograma'],
                ['id' => 'orcamentos', 'label' => 'Orùamentos'],
                ['id' => 'equipes', 'label' => 'Equipes de Campo'],
            ],
        ],
        [
            'id' => 'hub_portal',
            'label' => 'Nùcleo Portal do Colaborador',
            'scope' => 'hub_portal',
            'products' => [],
        ],
        [
            'id' => 'hub_recrutamento',
            'label' => 'Nùcleo Recrutamento',
            'scope' => 'hub_recrutamento',
            'products' => [
                ['id' => 'vagas', 'label' => 'Vagas'],
                ['id' => 'pipeline', 'label' => 'Pipeline'],
            ],
        ],
        [
            'id' => 'hub_esg',
            'label' => 'Nùcleo ESG',
            'scope' => 'hub_esg',
            'products' => [],
        ],
        [
            'id' => 'hub_suprimentos',
            'label' => 'Nùcleo Suprimentos',
            'scope' => 'hub_suprimentos',
            'products' => [],
        ],
        [
            'id' => 'hub_ti',
            'label' => 'Nùcleo TI',
            'scope' => 'hub_ti',
            'products' => [
                ['id' => 'chamados', 'label' => 'Chamados'],
                ['id' => 'catalogo', 'label' => 'Catùlogo'],
                ['id' => 'kb', 'label' => 'Base de Conhecimento'],
                ['id' => 'problemas', 'label' => 'Problemas'],
                ['id' => 'meus_chamados', 'label' => 'Meus Chamados'],
                ['id' => 'sla', 'label' => 'SLA'],
                ['id' => 'manutencoes', 'label' => 'Manutenùùes'],
                ['id' => 'ativos', 'label' => 'Ativos'],
                ['id' => 'licencas', 'label' => 'Licenùas'],
                ['id' => 'integracoes', 'label' => 'Integraùùes'],
                ['id' => 'cortex', 'label' => 'Cortex Ops'],
                ['id' => 'analytics', 'label' => 'Analytics'],
                ['id' => 'novidades', 'label' => 'Novidades'],
            ],
        ],
        [
            'id' => 'hub_expansao',
            'label' => 'Nùcleo Expansùo',
            'scope' => 'hub_expansao',
            'products' => [],
        ],
        [
            'id' => 'hub_qualidade',
            'label' => 'Nùcleo Qualidade',
            'scope' => 'hub_qualidade',
            'products' => [],
        ],
        [
            'id' => 'hub_facilities',
            'label' => 'Nùcleo Facilities',
            'scope' => 'hub_facilities',
            'products' => [],
        ],
        [
            'id' => 'hub_patrimonio',
            'label' => 'Nùcleo Patrimùnio',
            'scope' => 'hub_patrimonio',
            'products' => [],
        ],
        [
            'id' => 'hub_conhecimento',
            'label' => 'Nùcleo Conhecimento',
            'scope' => 'hub_conhecimento',
            'products' => [],
        ],
        [
            'id' => 'hub_integracoes',
            'label' => 'Nùcleo Integraùùes',
            'scope' => 'hub_integracoes',
            'products' => [
                ['id' => 'observatorio', 'label' => 'Observatùrio Causal'],
                ['id' => 'catalogo', 'label' => 'Catùlogo'],
                ['id' => 'conectores', 'label' => 'Conectores'],
                ['id' => 'webhooks', 'label' => 'Webhooks'],
                ['id' => 'mapeamentos', 'label' => 'Mapeamentos'],
                ['id' => 'api_keys', 'label' => 'API & chaves'],
                ['id' => 'logs', 'label' => 'Logs'],
                ['id' => 'playbooks', 'label' => 'Playbooks'],
            ],
        ],
        [
            'id' => 'hub_customer_success',
            'label' => 'Nùcleo Customer Success',
            'scope' => 'hub_customer_success',
            'products' => [],
        ],
        [
            'id' => 'hub_inovacao',
            'label' => 'Nùcleo Inovaùùo',
            'scope' => 'hub_inovacao',
            'products' => [
                ['id' => 'pipeline', 'label' => 'Pipeline'],
                ['id' => 'laboratorio', 'label' => 'Laboratùrio'],
                ['id' => 'experimentos', 'label' => 'Experimentos'],
                ['id' => 'backlog', 'label' => 'Backlog'],
                ['id' => 'analytics', 'label' => 'Analytics'],
                ['id' => 'conexoes', 'label' => 'Conexùes'],
                ['id' => 'impact', 'label' => 'Impacto'],
                ['id' => 'tendencias', 'label' => 'Tendùncias'],
                ['id' => 'portfolio', 'label' => 'Portfùlio'],
                ['id' => 'novidades', 'label' => 'Novidades'],
            ],
        ],
        [
            'id' => 'hub_holdings',
            'label' => 'Nùcleo Multi-empresa',
            'scope' => 'hub_holdings',
            'products' => [],
        ],
        [
            'id' => 'hub_seguros',
            'label' => 'Nùcleo Seguros',
            'scope' => 'hub_seguros',
            'products' => [],
        ],
        [
            'id' => 'hub_saude_ocupacional',
            'label' => 'Nùcleo Saùde Ocupacional',
            'scope' => 'hub_saude_ocupacional',
            'products' => [
                ['id' => 'pcmso', 'label' => 'PCMSO'],
                ['id' => 'exames', 'label' => 'Exames ocupacionais'],
                ['id' => 'aso', 'label' => 'ASO'],
                ['id' => 'agendamentos', 'label' => 'Agendamentos'],
                ['id' => 'afastamentos', 'label' => 'Afastamentos'],
                ['id' => 'prontuario', 'label' => 'Prontuùrio ocupacional'],
            ],
        ],
        [
            'id' => 'hub_pos_operatorio',
            'label' => 'Unio Saùde',
            'scope' => 'hub_pos_operatorio',
            'products' => [
                ['id' => 'pacientes', 'label' => 'Pacientes'],
                ['id' => 'protocolos', 'label' => 'Protocolos'],
                ['id' => 'questionarios', 'label' => 'Questionùrios'],
                ['id' => 'alertas', 'label' => 'Alertas clùnicos'],
                ['id' => 'painel', 'label' => 'Painel de recuperaùùo'],
                ['id' => 'portal_paciente', 'label' => 'Portal do paciente'],
            ],
        ],
        [
            'id' => 'hub_licitacoes',
            'label' => 'Nùcleo Licitaùùes',
            'scope' => 'hub_licitacoes',
            'products' => [],
        ],
        [
            'id' => 'hub_marketing',
            'label' => 'Nùcleo Marketing',
            'scope' => 'hub_marketing',
            'products' => [],
        ],
        [
            'id' => 'hub_lakehouse',
            'label' => 'Nùcleo Data & Lakehouse',
            'scope' => 'hub_lakehouse',
            'products' => [],
        ],
        [
            'id' => 'hub_franquias',
            'label' => 'Nùcleo Franquias & Unidades',
            'scope' => 'hub_franquias',
            'products' => [],
        ],
        [
            'id' => 'hub_seguranca_info',
            'label' => 'Nùcleo Seguranùa da Informaùùo',
            'scope' => 'hub_seguranca_info',
            'products' => [],
        ],
        [
            'id' => 'hub_pmo',
            'label' => 'Nùcleo PMO',
            'scope' => 'hub_pmo',
            'products' => [],
        ],
        [
            'id' => 'hub_treinamento_regulatorio',
            'label' => 'Nùcleo Treinamento Regulatùrio',
            'scope' => 'hub_treinamento_regulatorio',
            'products' => [],
        ],
        [
            'id' => 'hub_terceiros',
            'label' => 'Nùcleo Gestùo de Terceiros',
            'scope' => 'hub_terceiros',
            'products' => [],
        ],
        [
            'id' => 'product_rh',
            'label' => 'Recursos Humanos',
            'scope' => 'product_rh',
            'products' => [
                ['id' => 'funcionarios', 'label' => 'Funcionùrios'],
                ['id' => 'admissoes', 'label' => 'Admissùes'],
                ['id' => 'ferias', 'label' => 'Fùrias'],
                ['id' => 'folha', 'label' => 'Folha'],
                ['id' => 'portal', 'label' => 'Portal do colaborador'],
                ['id' => 'recrutamento', 'label' => 'Recrutamento'],
                ['id' => 'ponto', 'label' => 'Ponto'],
                ['id' => 'comunicacao', 'label' => 'Comunicaùùo'],
                ['id' => 'organograma', 'label' => 'Organograma'],
                ['id' => 'auditoria', 'label' => 'Auditoria'],
                ['id' => 'workflows', 'label' => 'Workflows'],
                ['id' => 'folha_legal', 'label' => 'Folha legal'],
                ['id' => 'contabilidade', 'label' => 'Provisùes'],
                ['id' => 'esocial', 'label' => 'eSocial'],
                ['id' => 'assinatura', 'label' => 'Assinatura digital'],
                ['id' => 'analytics', 'label' => 'Analytics RH'],
            ],
        ],
        [
            'id' => 'product_pessoas',
            'label' => 'Gestùo de Pessoas',
            'scope' => 'product_pessoas',
            'products' => [
                ['id' => 'membros', 'label' => 'Membros'],
                ['id' => 'equipes', 'label' => 'Equipes'],
                ['id' => 'cargos', 'label' => 'Cargos'],
                ['id' => 'avaliacao', 'label' => 'Avaliaùùo'],
            ],
        ],
        [
            'id' => 'product_engenharia',
            'label' => 'Obras e Projetos',
            'scope' => 'product_engenharia',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos'],
                ['id' => 'cronograma', 'label' => 'Cronograma'],
                ['id' => 'orcamentos', 'label' => 'Orùamentos'],
                ['id' => 'equipes', 'label' => 'Equipes de Campo'],
            ],
        ],
        [
            'id' => 'product_publicidade',
            'label' => 'Marca e Comunicaùùo',
            'scope' => 'product_publicidade',
            'products' => [
                ['id' => 'campanhas', 'label' => 'Campanhas'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'criativos', 'label' => 'Criativos'],
                ['id' => 'metricas', 'label' => 'Mùtricas'],
            ],
        ],
    ];

    /** @var array<string, array<string, array<string, string>>> scope => member_id => product_id => perfil_id */
    public const DEFAULT_GRANTS = [
        'hub_operacoes' => [
            'gestor' => ['rh' => 'GESTOR', 'pessoas' => 'GESTOR', 'engenharia' => 'GESTOR_EQUIPE'],
            'gestor-eq' => ['rh' => 'GESTOR_EQUIPE', 'pessoas' => 'GESTOR_EQUIPE', 'engenharia' => 'SUPERVISOR'],
            'supervisor' => ['rh' => 'SUPERVISOR', 'pessoas' => 'SUPERVISOR_EQUIPE', 'engenharia' => 'MEMBRO'],
            'sup-eq' => ['rh' => 'SUPERVISOR_EQUIPE', 'pessoas' => 'MEMBRO', 'engenharia' => 'MEMBRO'],
            'membro' => ['rh' => 'MEMBRO', 'pessoas' => 'MEMBRO'],
        ],
        'hub_talentos' => [
            'gestor' => ['banco' => 'GESTOR', 'vagas' => 'GESTOR', 'trilhas' => 'GESTOR_EQUIPE', 'mentorias' => 'GESTOR_EQUIPE'],
            'gestor-eq' => ['banco' => 'GESTOR_EQUIPE', 'vagas' => 'SUPERVISOR', 'trilhas' => 'SUPERVISOR_EQUIPE'],
            'supervisor' => ['banco' => 'SUPERVISOR', 'vagas' => 'SUPERVISOR_EQUIPE'],
            'membro' => ['banco' => 'MEMBRO'],
        ],
        'hub_maturidade' => [
            'gestor' => ['avaliacao' => 'GESTOR', 'plano' => 'GESTOR', 'historico' => 'GESTOR_EQUIPE', 'radar' => 'GESTOR_EQUIPE'],
            'supervisor' => ['avaliacao' => 'SUPERVISOR', 'plano' => 'SUPERVISOR_EQUIPE'],
            'membro' => ['avaliacao' => 'MEMBRO'],
        ],
        'hub_recrutamento' => [
            'gestor' => ['vagas' => 'GESTOR', 'pipeline' => 'GESTOR'],
            'gestor-eq' => ['vagas' => 'GESTOR_EQUIPE', 'pipeline' => 'SUPERVISOR'],
            'supervisor' => ['vagas' => 'SUPERVISOR', 'pipeline' => 'SUPERVISOR_EQUIPE'],
        ],
        'hub_admin' => [
            'gestor' => ['usuarios' => 'GESTOR', 'empresas' => 'GESTOR', 'configuracoes' => 'GESTOR_EQUIPE'],
        ],
        'product_rh' => [
            'gestor' => [
                'funcionarios' => 'GESTOR', 'admissoes' => 'GESTOR', 'ferias' => 'GESTOR_EQUIPE', 'folha' => 'GESTOR',
                'portal' => 'GESTOR_EQUIPE', 'recrutamento' => 'GESTOR', 'ponto' => 'GESTOR_EQUIPE',
                'comunicacao' => 'GESTOR', 'organograma' => 'GESTOR', 'auditoria' => 'GESTOR',
                'workflows' => 'GESTOR', 'folha_legal' => 'GESTOR', 'contabilidade' => 'GESTOR',
                'esocial' => 'GESTOR', 'assinatura' => 'GESTOR', 'analytics' => 'GESTOR',
            ],
            'supervisor' => [
                'funcionarios' => 'SUPERVISOR', 'ferias' => 'SUPERVISOR_EQUIPE', 'folha' => 'SUPERVISOR',
                'portal' => 'MEMBRO', 'recrutamento' => 'SUPERVISOR', 'ponto' => 'SUPERVISOR_EQUIPE',
                'comunicacao' => 'SUPERVISOR', 'organograma' => 'SUPERVISOR', 'auditoria' => 'SUPERVISOR',
                'workflows' => 'SUPERVISOR_EQUIPE', 'folha_legal' => 'SUPERVISOR', 'contabilidade' => 'SUPERVISOR',
                'esocial' => 'SUPERVISOR_EQUIPE', 'assinatura' => 'SUPERVISOR_EQUIPE', 'analytics' => 'SUPERVISOR',
            ],
            'membro' => [
                'funcionarios' => 'MEMBRO', 'portal' => 'MEMBRO', 'ponto' => 'MEMBRO', 'comunicacao' => 'MEMBRO',
                'organograma' => 'MEMBRO', 'analytics' => 'MEMBRO',
            ],
        ],
        'product_pessoas' => [
            'gestor' => ['membros' => 'GESTOR', 'equipes' => 'GESTOR', 'cargos' => 'GESTOR_EQUIPE', 'avaliacao' => 'GESTOR_EQUIPE'],
            'gestor-eq' => ['membros' => 'GESTOR_EQUIPE', 'equipes' => 'SUPERVISOR'],
            'supervisor' => ['membros' => 'SUPERVISOR_EQUIPE', 'equipes' => 'SUPERVISOR', 'cargos' => 'SUPERVISOR_EQUIPE', 'avaliacao' => 'SUPERVISOR'],
            'sup-eq' => ['membros' => 'MEMBRO', 'equipes' => 'SUPERVISOR_EQUIPE'],
            'membro' => ['membros' => 'MEMBRO'],
        ],
        'product_engenharia' => [
            'gestor' => ['projetos' => 'GESTOR', 'cronograma' => 'GESTOR_EQUIPE', 'orcamentos' => 'GESTOR', 'equipes' => 'SUPERVISOR'],
            'supervisor' => ['projetos' => 'SUPERVISOR_EQUIPE', 'equipes' => 'SUPERVISOR_EQUIPE'],
        ],
        'product_publicidade' => [
            'gestor' => ['campanhas' => 'GESTOR', 'clientes' => 'GESTOR', 'criativos' => 'GESTOR_EQUIPE', 'metricas' => 'GESTOR_EQUIPE'],
        ],
        'product_core' => [
            'gestor' => ['projetos' => 'GESTOR', 'metas' => 'GESTOR'],
            'gestor-eq' => ['projetos' => 'GESTOR_EQUIPE', 'metas' => 'GESTOR_EQUIPE'],
            'supervisor' => ['projetos' => 'SUPERVISOR', 'metas' => 'SUPERVISOR'],
        ],
        'hub_ti' => [
            'gestor' => [
                'chamados' => 'GESTOR', 'catalogo' => 'GESTOR', 'kb' => 'GESTOR', 'problemas' => 'GESTOR',
                'meus_chamados' => 'GESTOR', 'sla' => 'GESTOR', 'manutencoes' => 'GESTOR', 'ativos' => 'GESTOR',
                'licencas' => 'GESTOR', 'integracoes' => 'GESTOR', 'cortex' => 'GESTOR', 'analytics' => 'GESTOR',
                'novidades' => 'GESTOR',
            ],
            'gestor-eq' => [
                'chamados' => 'GESTOR_EQUIPE', 'catalogo' => 'GESTOR_EQUIPE', 'kb' => 'GESTOR_EQUIPE',
                'problemas' => 'GESTOR_EQUIPE', 'meus_chamados' => 'GESTOR_EQUIPE', 'sla' => 'GESTOR_EQUIPE',
                'manutencoes' => 'GESTOR_EQUIPE', 'ativos' => 'GESTOR_EQUIPE', 'licencas' => 'GESTOR_EQUIPE',
                'integracoes' => 'GESTOR_EQUIPE', 'cortex' => 'GESTOR_EQUIPE', 'analytics' => 'GESTOR_EQUIPE',
                'novidades' => 'GESTOR_EQUIPE',
            ],
            'supervisor' => [
                'chamados' => 'SUPERVISOR', 'catalogo' => 'SUPERVISOR', 'kb' => 'SUPERVISOR_EQUIPE',
                'problemas' => 'SUPERVISOR', 'meus_chamados' => 'MEMBRO', 'sla' => 'SUPERVISOR',
                'manutencoes' => 'SUPERVISOR', 'ativos' => 'SUPERVISOR', 'licencas' => 'SUPERVISOR',
                'integracoes' => 'SUPERVISOR', 'cortex' => 'SUPERVISOR', 'analytics' => 'SUPERVISOR',
                'novidades' => 'SUPERVISOR_EQUIPE',
            ],
            'sup-eq' => [
                'chamados' => 'SUPERVISOR_EQUIPE', 'catalogo' => 'SUPERVISOR_EQUIPE', 'kb' => 'SUPERVISOR_EQUIPE',
                'problemas' => 'SUPERVISOR_EQUIPE', 'meus_chamados' => 'MEMBRO', 'sla' => 'SUPERVISOR_EQUIPE',
                'manutencoes' => 'SUPERVISOR_EQUIPE', 'ativos' => 'SUPERVISOR_EQUIPE', 'licencas' => 'SUPERVISOR_EQUIPE',
                'integracoes' => 'SUPERVISOR_EQUIPE', 'cortex' => 'SUPERVISOR_EQUIPE', 'analytics' => 'SUPERVISOR_EQUIPE',
                'novidades' => 'MEMBRO',
            ],
            'membro' => [
                'catalogo' => 'MEMBRO', 'meus_chamados' => 'MEMBRO', 'novidades' => 'MEMBRO',
            ],
        ],
        'hub_pos_operatorio' => [
            'gestor' => [
                'pacientes' => 'GESTOR', 'protocolos' => 'GESTOR', 'questionarios' => 'GESTOR',
                'alertas' => 'GESTOR', 'painel' => 'GESTOR', 'portal_paciente' => 'GESTOR',
            ],
            'gestor-eq' => [
                'pacientes' => 'GESTOR_EQUIPE', 'protocolos' => 'GESTOR_EQUIPE', 'questionarios' => 'GESTOR_EQUIPE',
                'alertas' => 'GESTOR_EQUIPE', 'painel' => 'GESTOR_EQUIPE',
            ],
            'supervisor' => [
                'pacientes' => 'SUPERVISOR', 'alertas' => 'SUPERVISOR', 'painel' => 'SUPERVISOR',
                'questionarios' => 'SUPERVISOR_EQUIPE',
            ],
            'sup-eq' => [
                'pacientes' => 'SUPERVISOR_EQUIPE', 'alertas' => 'SUPERVISOR_EQUIPE', 'painel' => 'SUPERVISOR_EQUIPE',
            ],
            'membro' => [
                'portal_paciente' => 'MEMBRO', 'questionarios' => 'MEMBRO',
            ],
        ],
        'hub_saude_ocupacional' => [
            'gestor' => [
                'pcmso' => 'GESTOR', 'exames' => 'GESTOR', 'aso' => 'GESTOR',
                'agendamentos' => 'GESTOR', 'afastamentos' => 'GESTOR', 'prontuario' => 'GESTOR',
            ],
            'gestor-eq' => [
                'pcmso' => 'GESTOR_EQUIPE', 'exames' => 'GESTOR_EQUIPE', 'aso' => 'GESTOR_EQUIPE',
                'agendamentos' => 'GESTOR_EQUIPE', 'afastamentos' => 'GESTOR_EQUIPE', 'prontuario' => 'GESTOR_EQUIPE',
            ],
            'supervisor' => [
                'pcmso' => 'SUPERVISOR', 'exames' => 'SUPERVISOR', 'aso' => 'SUPERVISOR',
                'agendamentos' => 'SUPERVISOR_EQUIPE', 'afastamentos' => 'SUPERVISOR', 'prontuario' => 'SUPERVISOR_EQUIPE',
            ],
            'sup-eq' => [
                'exames' => 'SUPERVISOR_EQUIPE', 'aso' => 'SUPERVISOR_EQUIPE',
                'agendamentos' => 'SUPERVISOR_EQUIPE', 'afastamentos' => 'SUPERVISOR_EQUIPE',
            ],
            'membro' => [
                'agendamentos' => 'MEMBRO', 'aso' => 'MEMBRO',
            ],
        ],
        'hub_integracoes' => [
            'gestor' => [
                'observatorio' => 'GESTOR', 'catalogo' => 'GESTOR', 'conectores' => 'GESTOR', 'webhooks' => 'GESTOR',
                'mapeamentos' => 'GESTOR', 'api_keys' => 'GESTOR', 'logs' => 'GESTOR', 'playbooks' => 'GESTOR',
            ],
            'gestor-eq' => [
                'observatorio' => 'GESTOR_EQUIPE', 'catalogo' => 'GESTOR_EQUIPE', 'conectores' => 'GESTOR_EQUIPE', 'webhooks' => 'GESTOR_EQUIPE',
                'mapeamentos' => 'GESTOR_EQUIPE', 'api_keys' => 'GESTOR_EQUIPE', 'logs' => 'SUPERVISOR', 'playbooks' => 'GESTOR_EQUIPE',
            ],
            'supervisor' => [
                'observatorio' => 'SUPERVISOR', 'catalogo' => 'SUPERVISOR', 'conectores' => 'SUPERVISOR', 'webhooks' => 'SUPERVISOR',
                'mapeamentos' => 'SUPERVISOR_EQUIPE', 'api_keys' => 'SUPERVISOR', 'logs' => 'SUPERVISOR', 'playbooks' => 'SUPERVISOR',
            ],
            'sup-eq' => [
                'observatorio' => 'SUPERVISOR_EQUIPE', 'catalogo' => 'SUPERVISOR_EQUIPE', 'conectores' => 'SUPERVISOR_EQUIPE', 'webhooks' => 'SUPERVISOR_EQUIPE',
                'logs' => 'SUPERVISOR_EQUIPE', 'playbooks' => 'MEMBRO',
            ],
            'membro' => [
                'observatorio' => 'MEMBRO', 'catalogo' => 'MEMBRO', 'logs' => 'MEMBRO', 'playbooks' => 'MEMBRO',
            ],
        ],
    ];

    public function __construct(
        private UserRepository $userRepo,
        private UserProductGrantRepository $grantRepo,
        private FuncionarioRepository $funcionarioRepo,
        private WorkspaceService $workspace,
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getScopeData(string $scope): ?array
    {
        if (!isset(self::SCOPES[$scope])) {
            return null;
        }

        $def = self::SCOPES[$scope];
        $profiles = self::ASSIGNABLE_PROFILES;
        $profileMap = [];
        foreach ($profiles as $p) {
            $profileMap[$p['id']] = $p;
        }

        $empresa = $this->getActiveEmpresa();
        $members = $this->getMembers($empresa);
        if ($members === [] && isset(self::SCOPE_MOCK_MEMBERS[$scope])) {
            $members = $this->buildMockMembers($scope);
        }

        $rows = [];
        foreach ($members as $member) {
            $grants = $this->getGrantsForMemberScope($member['id'], $scope, $empresa);
            $allGrants = $this->getAllGrantsForMember($member['id'], $empresa);
            $rows[] = [
                'member' => $member,
                'grants' => $grants,
                'all_grants' => $allGrants,
                'grant_count' => \count($allGrants),
                'scope_summary' => $this->buildScopeGrantSummary($scope, $grants),
            ];
        }

        return [
            'scope' => $scope,
            'label' => $def['label'],
            'subtitle' => $def['subtitle'],
            'products' => $def['products'],
            'profiles' => $profiles,
            'profile_map' => $profileMap,
            'rows' => $rows,
            'all_hubs' => self::ALL_HUB_GROUPS,
            'no_access_description' => self::NO_ACCESS_DESCRIPTION,
        ];
    }

    /**
     * @return array<string, string> keys "scope:productId" => perfil_id
     */
    public function getAllGrantsForMember(string $memberId, ?Empresa $empresa = null): array
    {
        $empresa ??= $this->getActiveEmpresa();
        $user = $this->resolveUserForMemberId($memberId, $empresa);
        if ($user && $this->grantRepo->userHasConfiguredMatrix($user)) {
            return $this->grantRepo->findAllGrantKeysForUser($user);
        }

        if ($user) {
            $dbGrants = $this->grantRepo->findAllGrantKeysForUser($user);
            if ($dbGrants !== []) {
                return $dbGrants;
            }
        }

        $grants = [];
        foreach (self::DEFAULT_GRANTS as $scope => $members) {
            foreach ($members[$memberId] ?? [] as $productId => $profile) {
                $grants[$scope . ':' . $productId] = $profile;
            }
        }

        return $grants;
    }

    /**
     * @return array<string, string> productId => perfil_id
     */
    public function getGrantsForMemberScope(string $memberId, string $scope, ?Empresa $empresa = null): array
    {
        $empresa ??= $this->getActiveEmpresa();
        $user = $this->resolveUserForMemberId($memberId, $empresa);
        if ($user && $this->grantRepo->userHasConfiguredMatrix($user)) {
            return $this->grantRepo->findGrantMapForUserAndScope($user, $scope);
        }

        if ($user) {
            $dbGrants = $this->grantRepo->findGrantMapForUserAndScope($user, $scope);
            if ($dbGrants !== []) {
                return $dbGrants;
            }
        }

        return self::DEFAULT_GRANTS[$scope][$memberId] ?? [];
    }

    /**
     * Persiste grants granulares de um membro (substitui todos os registros do usuùrio).
     *
     * @param array<string, string> $grantsMap keys "scope:productId" => perfil_id (vazio = sem acesso)
     *
     * @return int nùmero de grants gravados
     */
    public function saveMemberGrants(string $memberId, array $grantsMap, User $editor): int
    {
        if (!$this->canEditorSaveGrants($editor, $grantsMap)) {
            throw new AccessDeniedException('Sem permissùo para alterar grants.');
        }

        $empresa = $this->workspace->getActiveEmpresa($editor) ?? $editor->getEmpresa();
        $target = $this->resolveUserForMemberId($memberId, $empresa);
        if (!$target) {
            throw new \InvalidArgumentException('Membro nùo encontrado nesta empresa.');
        }

        if (\in_array($target->getPerfil(), ['TENANT', 'PLATFORM_OWNER'], true)) {
            throw new \InvalidArgumentException('Permissùes de contas globais da plataforma nùo sùo editùveis.');
        }

        $grantsMap = $this->syncOperacoesHubGrants($grantsMap);

        $this->grantRepo->deleteAllForUser($target);
        $target->getProductGrants()->clear();

        $saved = 0;
        foreach ($grantsMap as $key => $perfilGrant) {
            if (!$perfilGrant) {
                continue;
            }

            if (!\is_string($key) || !str_contains($key, ':')) {
                continue;
            }

            [$scope, $productId] = explode(':', $key, 2);
            if (!$this->isValidGrantTarget($scope, $productId)) {
                continue;
            }

            if (!\in_array($perfilGrant, array_column(self::ASSIGNABLE_PROFILES, 'id'), true)) {
                continue;
            }

            $grant = (new UserProductGrant())
                ->setScope($scope)
                ->setProductId($productId)
                ->setPerfilGrant($perfilGrant);
            $target->addProductGrant($grant);
            $this->em->persist($grant);
            ++$saved;
        }

        $this->em->flush();

        $this->grantRepo->ensureConfiguredMarker($target);

        return $saved;
    }

    /**
     * Alinha hub_operacoes:rh|pessoas|engenharia com os grants de product_* (fonte da verdade na UI).
     *
     * @param array<string, string> $grantsMap
     *
     * @return array<string, string>
     */
    private function syncOperacoesHubGrants(array $grantsMap): array
    {
        $map = $grantsMap;
        $links = [
            'rh' => 'product_rh',
            'pessoas' => 'product_pessoas',
            'engenharia' => 'product_engenharia',
        ];

        foreach ($links as $hubProduct => $productScope) {
            $bestProfile = $this->highestProfileInScope($map, $productScope);
            $hubKey = 'hub_operacoes:' . $hubProduct;
            if ($bestProfile !== null) {
                $map[$hubKey] = $bestProfile;
            } else {
                unset($map[$hubKey]);
            }
        }

        return $map;
    }

    /**
     * @param array<string, string> $grantsMap
     */
    private function highestProfileInScope(array $grantsMap, string $scope): ?string
    {
        if (!isset(self::SCOPES[$scope])) {
            return null;
        }

        $bestLevel = 0;
        $bestProfile = null;
        foreach (self::SCOPES[$scope]['products'] as $product) {
            $profile = $grantsMap[$scope . ':' . $product['id']] ?? '';
            if ($profile === '') {
                continue;
            }
            $level = self::profileNivel($profile);
            if ($level > $bestLevel) {
                $bestLevel = $level;
                $bestProfile = $profile;
            }
        }

        return $bestProfile;
    }

    /**
     * @param array<string, string> $grantsInScope productId => perfil_id
     *
     * @return array{label: string, class: string, description: string}
     */
    public function buildScopeGrantSummary(string $scope, array $grantsInScope): array
    {
        if (!isset(self::SCOPES[$scope])) {
            return ['label' => 'ù', 'class' => 'none', 'description' => 'Escopo nùo encontrado.'];
        }

        $values = [];
        foreach (self::SCOPES[$scope]['products'] as $product) {
            $value = $grantsInScope[$product['id']] ?? '';
            if ($value !== '') {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return [
                'label' => 'Sem acesso',
                'class' => 'none',
                'description' => 'Sem permissùo neste escopo ù produtos desta aba bloqueados.',
            ];
        }

        $unique = array_values(array_unique($values));
        if (\count($unique) === 1) {
            foreach (self::ASSIGNABLE_PROFILES as $profile) {
                if ($profile['id'] === $unique[0]) {
                    return [
                        'label' => $profile['label'],
                        'class' => $profile['class'],
                        'description' => $profile['description'],
                    ];
                }
            }
        }

        return [
            'label' => 'Misto (' . \count($values) . ')',
            'class' => 'default',
            'description' => 'Perfis diferentes entre os produtos desta aba.',
        ];
    }

    private function isValidGrantTarget(string $scope, string $productId): bool
    {
        if (!isset(self::SCOPES[$scope])) {
            return false;
        }

        foreach (self::SCOPES[$scope]['products'] as $product) {
            if ($product['id'] === $productId) {
                return true;
            }
        }

        return false;
    }

    public static function memberIdFromEmail(string $email): string
    {
        $local = explode('@', $email)[0] ?? $email;

        return str_replace('.', '-', $local);
    }

    /**
     * Metadados ilustrativos (equipe/cargo) para contas demo ou legado do seed.
     *
     * @return array{equipe: string, cargo: string}|null
     */
    public static function memberMetaForEmail(string $email): ?array
    {
        if (isset(self::MEMBER_META[$email])) {
            return self::MEMBER_META[$email];
        }

        $legacy = DevSeedEmails::legacyEmailFor($email);
        if ($legacy !== null && isset(self::MEMBER_META[$legacy])) {
            return self::MEMBER_META[$legacy];
        }

        return null;
    }

    /**
     * Membros da empresa para busca global e painel de permissùes.
     *
     * @return list<array{id: string, nome: string, email: string, initials: string, equipe: string, cargo: string, perfil_global: string, perfil_label: string, perfil_class: string, ficha_id: int|null, user_id: int|null}>
     */
    public function getMembersForSearch(?Empresa $empresa = null): array
    {
        return $this->getMembers($empresa);
    }

    /**
     * @return list<array{id: string, nome: string, email: string, initials: string, equipe: string, cargo: string, perfil_global: string, perfil_label: string, perfil_class: string, ficha_id: int|null, user_id: int|null}>
     */
    private function getMembers(?Empresa $empresa): array
    {
        if (!$empresa) {
            return [];
        }

        $users = $this->userRepo->findBy(['empresa' => $empresa, 'ativo' => true], ['nome' => 'ASC']);
        $members = [];
        foreach ($users as $user) {
            if ($user->hasPlatformAccess()) {
                continue;
            }
            $members[] = $this->memberFromUser($user, $empresa);
        }

        return $members;
    }

    /**
     * @return list<array{id: string, nome: string, email: string, initials: string, equipe: string, cargo: string, perfil_global: string, perfil_label: string, perfil_class: string, ficha_id: int|null, user_id: int|null}>
     */
    private function buildMockMembers(string $scope): array
    {
        $members = [];
        foreach (self::SCOPE_MOCK_MEMBERS[$scope] ?? [] as $def) {
            $members[] = $this->member(
                $def['id'],
                $def['nome'],
                $def['email'],
                $def['perfil'],
                $def['equipe'],
                $def['cargo'],
                null,
                null,
            );
        }

        return $members;
    }

    private function memberFromUser(User $user, Empresa $empresa): array
    {
        $email = (string) $user->getEmail();
        $funcionario = $this->funcionarioRepo->findOneByUser($empresa, $user)
            ?? $this->funcionarioRepo->findOneByEmail($empresa, $email);

        $equipe = 'ù';
        $cargo = $user->getPerfilLabel();
        if ($funcionario) {
            $cargo = $funcionario->getCargo() ?: $cargo;
            $departamento = $funcionario->getDepartamento();
            if ($departamento !== null && $departamento->getNome() !== '') {
                $equipe = (string) $departamento->getNome();
            }
        } else {
            $meta = self::MEMBER_META[$email] ?? null;
            if ($meta !== null) {
                $equipe = $meta['equipe'];
                $cargo = $meta['cargo'];
            }
        }

        return $this->member(
            self::memberIdFromEmail($email),
            (string) $user->getNome(),
            $email,
            $user->getPerfil(),
            $equipe,
            $cargo,
            $funcionario?->getId(),
            $user->getId(),
        );
    }

    private function resolveUserForMemberId(string $memberId, ?Empresa $empresa): ?User
    {
        if (!$empresa) {
            return null;
        }

        foreach ($this->userRepo->findBy(['empresa' => $empresa, 'ativo' => true]) as $user) {
            if (self::memberIdFromEmail((string) $user->getEmail()) === $memberId) {
                return $user;
            }
        }

        return null;
    }

    private function getActiveEmpresa(): ?Empresa
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->workspace->getActiveEmpresa($user);
    }

    /**
     * @return array{id: string, nome: string, email: string, initials: string, equipe: string, cargo: string, perfil_global: string, perfil_label: string, perfil_class: string, ficha_id: int|null, user_id: int|null}
     */
    private function member(string $id, string $nome, string $email, string $perfil, string $equipe, string $cargo, ?int $fichaId, ?int $userId): array
    {
        $parts = preg_split('/\s+/', trim($nome), 2);
        $initials = mb_strtoupper(mb_substr($parts[0] ?? 'U', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));

        return [
            'id' => $id,
            'nome' => $nome,
            'email' => $email,
            'initials' => $initials ?: 'U',
            'equipe' => $equipe,
            'cargo' => $cargo,
            'perfil_global' => $perfil,
            'perfil_label' => match ($perfil) {
                'MEMBRO' => 'Membro',
                'SUPERVISOR_EQUIPE' => 'Supervisor de Equipe',
                'SUPERVISOR' => 'Supervisor Geral',
                'GESTOR_EQUIPE' => 'Gestor de Equipe',
                'GESTOR' => 'Gestor',
                default => $perfil,
            },
            'perfil_class' => match ($perfil) {
                'MEMBRO' => 'membro',
                'SUPERVISOR_EQUIPE' => 'supervisor-equipe',
                'SUPERVISOR' => 'supervisor',
                'GESTOR_EQUIPE' => 'gestor-equipe',
                'GESTOR' => 'gestor',
                default => 'default',
            },
            'ficha_id' => $fichaId,
            'user_id' => $userId,
        ];
    }
}
