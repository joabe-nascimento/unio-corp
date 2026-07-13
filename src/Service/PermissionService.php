<?php

namespace App\Service;

use App\Clinic\ClinicStaffRole;
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
 * Escopos de permiss?o por hub/produto.
 * Membros v?m do banco (empresa ativa); grants do banco com fallback em DEFAULT_GRANTS.
 * Tenant n?o aparece na matriz: acesso total impl?cito.
 */
class PermissionService
{
    /** @var list<array{id: string, label: string, class: string, nivel: int, description: string}> */
    public const ASSIGNABLE_PROFILES = [
        ['id' => 'MEMBRO', 'label' => 'Membro', 'class' => 'membro', 'nivel' => 1, 'description' => 'Acesso de participa??o: visualiza e usa o produto, sem gerenciar pessoas ou configura??es.'],
        ['id' => 'SUPERVISOR_EQUIPE', 'label' => 'Supervisor de Equipe', 'class' => 'supervisor-equipe', 'nivel' => 2, 'description' => 'Coordena a equipe no produto: acompanha entregas, aprova a??es do time e orienta o dia a dia.'],
        ['id' => 'SUPERVISOR', 'label' => 'Supervisor Geral', 'class' => 'supervisor', 'nivel' => 3, 'description' => 'Supervisiona v?rias equipes ou frentes do hub, com vis?o ampla de processos e indicadores.'],
        ['id' => 'GESTOR_EQUIPE', 'label' => 'Gestor de Equipe', 'class' => 'gestor-equipe', 'nivel' => 4, 'description' => 'Gerencia membros e permiss?es da equipe nos produtos em que atua.'],
        ['id' => 'GESTOR', 'label' => 'Gestor', 'class' => 'gestor', 'nivel' => 5, 'description' => 'Controle amplo do produto ou m?dulo: configura??es, acessos e opera??o completa da ?rea.'],
    ];

    /** Nível numérico do perfil assignável (para comparar grants). */
    public static function profileNivel(string $profileId): int
    {
        foreach (self::assignableProfilesForScope(null) as $profile) {
            if ($profile['id'] === $profileId) {
                return $profile['nivel'];
            }
        }

        return 0;
    }

    public static function profileLabel(string $profileId): string
    {
        foreach (self::assignableProfilesForScope(null) as $profile) {
            if ($profile['id'] === $profileId) {
                return $profile['label'];
            }
        }

        return $profileId;
    }

    public static function profileClass(string $profileId): string
    {
        foreach (self::assignableProfilesForScope(null) as $profile) {
            if ($profile['id'] === $profileId) {
                return $profile['class'];
            }
        }

        return 'default';
    }

    /**
     * @return list<array{id: string, label: string, class: string, nivel: int, description: string}>
     */
    public static function assignableProfilesForScope(?string $scope): array
    {
        if ($scope === ClinicStaffRole::SCOPE) {
            return ClinicStaffRole::assignableProfiles();
        }

        if ($scope === null) {
            return array_merge(self::ASSIGNABLE_PROFILES, ClinicStaffRole::assignableProfiles());
        }

        return self::ASSIGNABLE_PROFILES;
    }

    /** Painel/aba Permissões — perfil global ou grant ≥ Gestor de Equipe no escopo. */
    public function canManagePermissions(User $user, ?string $scope = null): bool
    {
        if ($user->hasPlatformAccess()) {
            return true;
        }

        if (\in_array($user->getPerfil(), ['GESTOR', 'GESTOR_EQUIPE', ClinicStaffRole::COORDENACAO], true)) {
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
     * Editor com grant granular s? pode salvar escopos em que ? gestor.
     *
     * @param array<string, string> $grantsMap keys "scope:productId" => perfil_id
     */
    public function canEditorSaveGrants(User $editor, array $grantsMap): bool
    {
        if ($this->canManagePermissions($editor)) {
            if ($editor->hasPlatformAccess()
                || \in_array($editor->getPerfil(), ['GESTOR', 'GESTOR_EQUIPE', ClinicStaffRole::COORDENACAO], true)) {
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

    private const NO_ACCESS_DESCRIPTION = 'Sem permiss?o neste produto ou hub ? o membro n?o consegue acessar a ?rea.';

    /** @var array<string, array{equipe: string, cargo: string}> */
    private const MEMBER_META = [
        DevSeedEmails::RENATA => ['equipe' => 'PMO', 'cargo' => 'Gestor de Operacoes'],
        DevSeedEmails::RICARDO => ['equipe' => 'Squad Backend', 'cargo' => 'Gestor de Equipe'],
        DevSeedEmails::ANA_PAULA => ['equipe' => 'Cirurgia geral', 'cargo' => 'Supervisor Geral'],
        DevSeedEmails::FELIPE => ['equipe' => 'Obras e Projetos', 'cargo' => 'Supervisor de Campo'],
        DevSeedEmails::LUCAS => ['equipe' => 'Design & Marca', 'cargo' => 'Analista'],
        DevSeedEmails::MARCELA => ['equipe' => 'Nexus Saúde', 'cargo' => 'Gestora'],
        DevSeedEmails::PATRICIA => ['equipe' => 'Edu360', 'cargo' => 'Gestora'],
        DevSeedEmails::CAMILA_RECEPCAO => ['equipe' => 'Recepção', 'cargo' => 'Recepcionista'],
        DevSeedEmails::BEATRIZ_ENFERMAGEM => ['equipe' => 'Enfermagem', 'cargo' => 'Enfermeira'],
        DevSeedEmails::ANDRE_MEDICO => ['equipe' => 'Clínica', 'cargo' => 'Médico'],
        DevSeedEmails::HELENA_COORDENACAO => ['equipe' => 'Coordenação', 'cargo' => 'Coordenadora clínica'],
    ];

    /**
     * Membros ilustrativos quando a empresa ativa n?o tem usu?rios no banco (preview de hubs).
     *
     * @var array<string, list<array{id: string, nome: string, email: string, perfil: string, equipe: string, cargo: string}>>
     */
    private const SCOPE_MOCK_MEMBERS = [
        'hub_pos_operatorio' => [
            [
                'id' => 'helena-castro',
                'nome' => 'Helena Castro',
                'email' => DevSeedEmails::HELENA_COORDENACAO,
                'perfil' => 'COORDENACAO',
                'equipe' => 'Coordenação',
                'cargo' => 'Coordenadora clínica',
            ],
            [
                'id' => 'andre-melo',
                'nome' => 'André Melo',
                'email' => DevSeedEmails::ANDRE_MEDICO,
                'perfil' => 'MEDICO',
                'equipe' => 'Clínica',
                'cargo' => 'Médico',
            ],
            [
                'id' => 'beatriz-nunes',
                'nome' => 'Beatriz Nunes',
                'email' => DevSeedEmails::BEATRIZ_ENFERMAGEM,
                'perfil' => 'ENFERMAGEM',
                'equipe' => 'Enfermagem',
                'cargo' => 'Enfermeira',
            ],
            [
                'id' => 'camila-souza',
                'nome' => 'Camila Souza',
                'email' => DevSeedEmails::CAMILA_RECEPCAO,
                'perfil' => 'RECEPCAO',
                'equipe' => 'Recepção',
                'cargo' => 'Recepcionista',
            ],
        ],
    ];

    /** @var array<string, array{label: string, subtitle: string, products: list<array{id: string, label: string}>}> */
    public const SCOPES = [
        'hub_operacoes' => [
            'label' => 'N?cleo de Opera??es',
            'subtitle' => 'Permiss?es por produto deste hub',
            'products' => [
                ['id' => 'rh', 'label' => 'Recursos Humanos'],
                ['id' => 'pessoas', 'label' => 'Gest?o de Pessoas'],
                ['id' => 'engenharia', 'label' => 'Obras e Projetos'],
            ],
        ],
        'hub_talentos' => [
            'label' => 'N?cleo de Talentos',
            'subtitle' => 'Permiss?es por produto deste hub',
            'products' => [
                ['id' => 'banco', 'label' => 'Banco de Talentos'],
                ['id' => 'vagas', 'label' => 'Vagas'],
                ['id' => 'trilhas', 'label' => 'Trilhas de Carreira'],
                ['id' => 'mentorias', 'label' => 'Mentorias'],
            ],
        ],
        'hub_maturidade' => [
            'label' => 'N?cleo de Maturidade',
            'subtitle' => 'Permiss?es por produto deste hub',
            'products' => [
                ['id' => 'avaliacao', 'label' => 'Avalia??o'],
                ['id' => 'plano', 'label' => 'Plano de A??o'],
                ['id' => 'historico', 'label' => 'Hist?rico'],
                ['id' => 'radar', 'label' => 'Radar'],
            ],
        ],
        'hub_admin' => [
            'label' => 'Administra??o',
            'subtitle' => 'Permiss?es da plataforma',
            'products' => [
                ['id' => 'usuarios', 'label' => 'Usu?rios'],
                ['id' => 'empresas', 'label' => 'Empresas'],
                ['id' => 'configuracoes', 'label' => 'Configura??es'],
            ],
        ],
        'hub_comercial' => [
            'label' => 'N?cleo Comercial',
            'subtitle' => 'CRM e pipeline comercial',
            'products' => [
                ['id' => 'leads', 'label' => 'Leads'],
                ['id' => 'pipeline', 'label' => 'Pipeline'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'atividades', 'label' => 'Atividades'],
                ['id' => 'analytics', 'label' => 'Analytics'],
            ],
        ],
        'hub_beneficios' => [
            'label' => 'N?cleo Benef?cios',
            'subtitle' => 'Marketplace de benef?cios',
            'products' => [],
        ],
        'hub_academy' => [
            'label' => 'N?cleo Academy',
            'subtitle' => 'Educa??o e trilhas de aprendizado',
            'products' => [],
        ],
        'hub_parceiros' => [
            'label' => 'N?cleo Parceiros',
            'subtitle' => 'Rede de parceiros e revenda',
            'products' => [],
        ],
        'hub_financeiro' => [
            'label' => 'N?cleo Financeiro',
            'subtitle' => 'Tesouraria e or?amento de pessoal',
            'products' => [],
        ],
        'hub_compliance' => [
            'label' => 'N?cleo Compliance',
            'subtitle' => 'Normas, LGPD e auditorias',
            'products' => [],
        ],
        'hub_analytics' => [
            'label' => 'N?cleo Analytics',
            'subtitle' => 'BI e indicadores',
            'products' => [],
        ],
        'hub_juridico' => [
            'label' => 'N?cleo Jur?dico',
            'subtitle' => 'Trabalhista e contratos',
            'products' => [],
        ],
        'hub_clima' => [
            'label' => 'N?cleo Clima',
            'subtitle' => 'Engajamento e eNPS',
            'products' => [],
        ],
        'hub_sst' => [
            'label' => 'N?cleo SST',
            'subtitle' => 'Sa?de e seguran?a do trabalho',
            'products' => [],
        ],
        'hub_comunicacao' => [
            'label' => 'N?cleo Comunica??o',
            'subtitle' => 'Mural e cultura interna',
            'products' => [],
        ],
        'hub_publicidade' => [
            'label' => 'N?cleo Publicidade',
            'subtitle' => 'Marca, campanhas e criativos',
            'products' => [
                ['id' => 'campanhas', 'label' => 'Campanhas'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'criativos', 'label' => 'Criativos'],
                ['id' => 'metricas', 'label' => 'M?tricas'],
            ],
        ],
        'hub_obras' => [
            'label' => 'N?cleo Obras e Projetos',
            'subtitle' => 'Engenharia e execu??o de obras',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos'],
                ['id' => 'cronograma', 'label' => 'Cronograma'],
                ['id' => 'orcamentos', 'label' => 'Or?amentos'],
                ['id' => 'equipes', 'label' => 'Equipes de Campo'],
            ],
        ],
        'hub_portal' => [
            'label' => 'N?cleo Portal do Colaborador',
            'subtitle' => 'Autoservi?o do colaborador',
            'products' => [],
        ],
        'hub_recrutamento' => [
            'label' => 'N?cleo Recrutamento',
            'subtitle' => 'Sele??o e pipeline de talentos',
            'products' => [
                ['id' => 'vagas', 'label' => 'Vagas'],
                ['id' => 'pipeline', 'label' => 'Pipeline'],
            ],
        ],
        'hub_esg' => [
            'label' => 'N?cleo ESG',
            'subtitle' => 'Sustentabilidade e impacto',
            'products' => [],
        ],
        'hub_suprimentos' => [
            'label' => 'N?cleo Suprimentos',
            'subtitle' => 'Compras e estoque',
            'products' => [],
        ],
        'hub_ti' => [
            'label' => 'N?cleo TI',
            'subtitle' => 'NOC Center ? Service desk',
            'products' => [
                ['id' => 'chamados', 'label' => 'Chamados'],
                ['id' => 'catalogo', 'label' => 'Cat?logo'],
                ['id' => 'kb', 'label' => 'Base de Conhecimento'],
                ['id' => 'problemas', 'label' => 'Problemas'],
                ['id' => 'meus_chamados', 'label' => 'Meus Chamados'],
                ['id' => 'sla', 'label' => 'SLA'],
                ['id' => 'manutencoes', 'label' => 'Manuten??es'],
                ['id' => 'ativos', 'label' => 'Ativos'],
                ['id' => 'licencas', 'label' => 'Licen?as'],
                ['id' => 'integracoes', 'label' => 'Integra??es'],
                ['id' => 'cortex', 'label' => 'Cortex Ops'],
                ['id' => 'analytics', 'label' => 'Analytics'],
                ['id' => 'novidades', 'label' => 'Novidades'],
            ],
        ],
        'hub_expansao' => [
            'label' => 'N?cleo Expans?o',
            'subtitle' => 'Franquias e novos mercados',
            'products' => [],
        ],
        'hub_qualidade' => [
            'label' => 'N?cleo Qualidade',
            'subtitle' => 'ISO, auditorias e n?o conformidades',
            'products' => [],
        ],
        'hub_facilities' => [
            'label' => 'N?cleo Facilities',
            'subtitle' => 'Predial, frota e manuten??o',
            'products' => [],
        ],
        'hub_patrimonio' => [
            'label' => 'N?cleo Patrim?nio',
            'subtitle' => 'Invent?rio e ativos',
            'products' => [],
        ],
        'hub_conhecimento' => [
            'label' => 'N?cleo Conhecimento',
            'subtitle' => 'Wiki, SOPs e playbooks',
            'products' => [],
        ],
        'hub_integracoes' => [
            'label' => 'N?cleo Integra??es',
            'subtitle' => 'APIs, conectores e webhooks',
            'products' => [
                ['id' => 'observatorio', 'label' => 'Observat?rio Causal'],
                ['id' => 'catalogo', 'label' => 'Cat?logo'],
                ['id' => 'conectores', 'label' => 'Conectores'],
                ['id' => 'webhooks', 'label' => 'Webhooks'],
                ['id' => 'mapeamentos', 'label' => 'Mapeamentos'],
                ['id' => 'api_keys', 'label' => 'API & chaves'],
                ['id' => 'logs', 'label' => 'Logs'],
                ['id' => 'playbooks', 'label' => 'Playbooks'],
            ],
        ],
        'hub_customer_success' => [
            'label' => 'N?cleo Customer Success',
            'subtitle' => 'P?s-venda e reten??o',
            'products' => [],
        ],
        'hub_inovacao' => [
            'label' => 'N?cleo Inova??o',
            'subtitle' => 'Labs e experimentos',
            'products' => [
                ['id' => 'pipeline', 'label' => 'Pipeline'],
                ['id' => 'laboratorio', 'label' => 'Laborat?rio'],
                ['id' => 'experimentos', 'label' => 'Experimentos'],
                ['id' => 'backlog', 'label' => 'Backlog'],
                ['id' => 'analytics', 'label' => 'Analytics'],
                ['id' => 'conexoes', 'label' => 'Conex?es'],
                ['id' => 'impact', 'label' => 'Impacto'],
                ['id' => 'tendencias', 'label' => 'Tend?ncias'],
                ['id' => 'portfolio', 'label' => 'Portf?lio'],
                ['id' => 'novidades', 'label' => 'Novidades'],
            ],
        ],
        'hub_holdings' => [
            'label' => 'N?cleo Multi-empresa',
            'subtitle' => 'Holdings e vis?o consolidada',
            'products' => [],
        ],
        'hub_seguros' => [
            'label' => 'N?cleo Seguros',
            'subtitle' => 'Seguros e benef?cios corporativos',
            'products' => [],
        ],
        'hub_saude_ocupacional' => [
            'label' => 'N?cleo Sa?de Ocupacional',
            'subtitle' => 'PCMSO, exames e medicina do trabalho',
            'products' => [
                ['id' => 'pcmso', 'label' => 'PCMSO'],
                ['id' => 'exames', 'label' => 'Exames ocupacionais'],
                ['id' => 'aso', 'label' => 'ASO'],
                ['id' => 'agendamentos', 'label' => 'Agendamentos'],
                ['id' => 'afastamentos', 'label' => 'Afastamentos'],
                ['id' => 'prontuario', 'label' => 'Prontu?rio ocupacional'],
            ],
        ],
        'hub_pos_operatorio' => [
            'label' => 'Unio Saúde',
            'subtitle' => 'Acompanhamento clínico pós-cirúrgico',
            'products' => [
                ['id' => 'pacientes', 'label' => 'Pacientes'],
                ['id' => 'operacao', 'label' => 'Operação / recepção'],
                ['id' => 'protocolos', 'label' => 'Protocolos'],
                ['id' => 'questionarios', 'label' => 'Questionários'],
                ['id' => 'alertas', 'label' => 'Alertas clínicos'],
                ['id' => 'painel', 'label' => 'Painel de recuperação'],
                ['id' => 'portal_paciente', 'label' => 'Portal do paciente'],
                ['id' => 'relatorios', 'label' => 'Relatórios'],
                ['id' => 'configuracoes', 'label' => 'Configurações'],
            ],
        ],
        'hub_licitacoes' => [
            'label' => 'N?cleo Licita??es',
            'subtitle' => 'Contratos p?blicos e B2G',
            'products' => [],
        ],
        'hub_marketing' => [
            'label' => 'N?cleo Marketing',
            'subtitle' => 'Demand gen, leads e campanhas',
            'products' => [],
        ],
        'hub_lakehouse' => [
            'label' => 'N?cleo Data & Lakehouse',
            'subtitle' => 'Dados brutos e pipelines',
            'products' => [],
        ],
        'hub_franquias' => [
            'label' => 'N?cleo Franquias & Unidades',
            'subtitle' => 'Rede de unidades e franqueados',
            'products' => [],
        ],
        'hub_seguranca_info' => [
            'label' => 'N?cleo Seguran?a da Informa??o',
            'subtitle' => 'LGPD t?cnica e incidentes',
            'products' => [],
        ],
        'hub_pmo' => [
            'label' => 'N?cleo PMO',
            'subtitle' => 'Projetos internos e governan?a',
            'products' => [],
        ],
        'hub_treinamento_regulatorio' => [
            'label' => 'N?cleo Treinamento Regulat?rio',
            'subtitle' => 'NR, certifica??es e obriga??es',
            'products' => [],
        ],
        'hub_terceiros' => [
            'label' => 'N?cleo Gest?o de Terceiros',
            'subtitle' => 'PJ, fornecedores e m?o de obra',
            'products' => [],
        ],
        'product_rh' => [
            'label' => 'Recursos Humanos',
            'subtitle' => 'Permiss?es por ?rea do m?dulo',
            'products' => [
                ['id' => 'funcionarios', 'label' => 'Funcion?rios'],
                ['id' => 'admissoes', 'label' => 'Admiss?es'],
                ['id' => 'ferias', 'label' => 'F?rias'],
                ['id' => 'folha', 'label' => 'Folha'],
                ['id' => 'portal', 'label' => 'Portal do colaborador'],
                ['id' => 'recrutamento', 'label' => 'Recrutamento'],
                ['id' => 'ponto', 'label' => 'Ponto'],
                ['id' => 'comunicacao', 'label' => 'Comunica??o'],
                ['id' => 'organograma', 'label' => 'Organograma'],
                ['id' => 'auditoria', 'label' => 'Auditoria'],
                ['id' => 'workflows', 'label' => 'Workflows'],
                ['id' => 'folha_legal', 'label' => 'Folha legal'],
                ['id' => 'contabilidade', 'label' => 'Provis?es'],
                ['id' => 'esocial', 'label' => 'eSocial'],
                ['id' => 'assinatura', 'label' => 'Assinatura digital'],
                ['id' => 'analytics', 'label' => 'Analytics RH'],
            ],
        ],
        'product_pessoas' => [
            'label' => 'Gest?o de Pessoas',
            'subtitle' => 'Permiss?es por ?rea do m?dulo',
            'products' => [
                ['id' => 'membros', 'label' => 'Membros'],
                ['id' => 'equipes', 'label' => 'Equipes'],
                ['id' => 'cargos', 'label' => 'Cargos'],
                ['id' => 'avaliacao', 'label' => 'Avalia??o'],
            ],
        ],
        'product_engenharia' => [
            'label' => 'Obras e Projetos',
            'subtitle' => 'Permiss?es por ?rea do m?dulo',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos'],
                ['id' => 'cronograma', 'label' => 'Cronograma'],
                ['id' => 'orcamentos', 'label' => 'Or?amentos'],
                ['id' => 'equipes', 'label' => 'Equipes de Campo'],
            ],
        ],
        'product_publicidade' => [
            'label' => 'Marca e Comunica??o',
            'subtitle' => 'Permiss?es por ?rea do m?dulo',
            'products' => [
                ['id' => 'campanhas', 'label' => 'Campanhas'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'criativos', 'label' => 'Criativos'],
                ['id' => 'metricas', 'label' => 'M?tricas'],
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
            'label' => 'N?cleo de Opera??es',
            'scope' => 'hub_operacoes',
            'products' => [
                ['id' => 'rh', 'label' => 'Recursos Humanos'],
                ['id' => 'pessoas', 'label' => 'Gest?o de Pessoas'],
                ['id' => 'engenharia', 'label' => 'Obras e Projetos'],
            ],
        ],
        [
            'id' => 'hub_talentos',
            'label' => 'N?cleo de Talentos',
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
            'label' => 'N?cleo de Maturidade',
            'scope' => 'hub_maturidade',
            'products' => [
                ['id' => 'avaliacao', 'label' => 'Avalia??o'],
                ['id' => 'plano', 'label' => 'Plano de A??o'],
                ['id' => 'historico', 'label' => 'Hist?rico'],
                ['id' => 'radar', 'label' => 'Radar'],
            ],
        ],
        [
            'id' => 'hub_admin',
            'label' => 'Administra??o',
            'scope' => 'hub_admin',
            'products' => [
                ['id' => 'usuarios', 'label' => 'Usu?rios'],
                ['id' => 'empresas', 'label' => 'Empresas'],
                ['id' => 'configuracoes', 'label' => 'Configura??es'],
            ],
        ],
        [
            'id' => 'hub_comercial',
            'label' => 'N?cleo Comercial',
            'scope' => 'hub_comercial',
            'products' => [
                ['id' => 'leads', 'label' => 'Leads'],
                ['id' => 'pipeline', 'label' => 'Pipeline'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'atividades', 'label' => 'Atividades'],
                ['id' => 'analytics', 'label' => 'Analytics'],
            ],
        ],
        [
            'id' => 'hub_beneficios',
            'label' => 'N?cleo Benef?cios',
            'scope' => 'hub_beneficios',
            'products' => [],
        ],
        [
            'id' => 'hub_academy',
            'label' => 'N?cleo Academy',
            'scope' => 'hub_academy',
            'products' => [],
        ],
        [
            'id' => 'hub_parceiros',
            'label' => 'N?cleo Parceiros',
            'scope' => 'hub_parceiros',
            'products' => [],
        ],
        [
            'id' => 'hub_financeiro',
            'label' => 'N?cleo Financeiro',
            'scope' => 'hub_financeiro',
            'products' => [],
        ],
        [
            'id' => 'hub_compliance',
            'label' => 'N?cleo Compliance',
            'scope' => 'hub_compliance',
            'products' => [],
        ],
        [
            'id' => 'hub_analytics',
            'label' => 'N?cleo Analytics',
            'scope' => 'hub_analytics',
            'products' => [],
        ],
        [
            'id' => 'hub_juridico',
            'label' => 'N?cleo Jur?dico',
            'scope' => 'hub_juridico',
            'products' => [],
        ],
        [
            'id' => 'hub_clima',
            'label' => 'N?cleo Clima',
            'scope' => 'hub_clima',
            'products' => [],
        ],
        [
            'id' => 'hub_sst',
            'label' => 'N?cleo SST',
            'scope' => 'hub_sst',
            'products' => [],
        ],
        [
            'id' => 'hub_comunicacao',
            'label' => 'N?cleo Comunica??o',
            'scope' => 'hub_comunicacao',
            'products' => [],
        ],
        [
            'id' => 'hub_publicidade',
            'label' => 'N?cleo Publicidade',
            'scope' => 'hub_publicidade',
            'products' => [
                ['id' => 'campanhas', 'label' => 'Campanhas'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'criativos', 'label' => 'Criativos'],
                ['id' => 'metricas', 'label' => 'M?tricas'],
            ],
        ],
        [
            'id' => 'hub_obras',
            'label' => 'N?cleo Obras e Projetos',
            'scope' => 'hub_obras',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos'],
                ['id' => 'cronograma', 'label' => 'Cronograma'],
                ['id' => 'orcamentos', 'label' => 'Or?amentos'],
                ['id' => 'equipes', 'label' => 'Equipes de Campo'],
            ],
        ],
        [
            'id' => 'hub_portal',
            'label' => 'N?cleo Portal do Colaborador',
            'scope' => 'hub_portal',
            'products' => [],
        ],
        [
            'id' => 'hub_recrutamento',
            'label' => 'N?cleo Recrutamento',
            'scope' => 'hub_recrutamento',
            'products' => [
                ['id' => 'vagas', 'label' => 'Vagas'],
                ['id' => 'pipeline', 'label' => 'Pipeline'],
            ],
        ],
        [
            'id' => 'hub_esg',
            'label' => 'N?cleo ESG',
            'scope' => 'hub_esg',
            'products' => [],
        ],
        [
            'id' => 'hub_suprimentos',
            'label' => 'N?cleo Suprimentos',
            'scope' => 'hub_suprimentos',
            'products' => [],
        ],
        [
            'id' => 'hub_ti',
            'label' => 'N?cleo TI',
            'scope' => 'hub_ti',
            'products' => [
                ['id' => 'chamados', 'label' => 'Chamados'],
                ['id' => 'catalogo', 'label' => 'Cat?logo'],
                ['id' => 'kb', 'label' => 'Base de Conhecimento'],
                ['id' => 'problemas', 'label' => 'Problemas'],
                ['id' => 'meus_chamados', 'label' => 'Meus Chamados'],
                ['id' => 'sla', 'label' => 'SLA'],
                ['id' => 'manutencoes', 'label' => 'Manuten??es'],
                ['id' => 'ativos', 'label' => 'Ativos'],
                ['id' => 'licencas', 'label' => 'Licen?as'],
                ['id' => 'integracoes', 'label' => 'Integra??es'],
                ['id' => 'cortex', 'label' => 'Cortex Ops'],
                ['id' => 'analytics', 'label' => 'Analytics'],
                ['id' => 'novidades', 'label' => 'Novidades'],
            ],
        ],
        [
            'id' => 'hub_expansao',
            'label' => 'N?cleo Expans?o',
            'scope' => 'hub_expansao',
            'products' => [],
        ],
        [
            'id' => 'hub_qualidade',
            'label' => 'N?cleo Qualidade',
            'scope' => 'hub_qualidade',
            'products' => [],
        ],
        [
            'id' => 'hub_facilities',
            'label' => 'N?cleo Facilities',
            'scope' => 'hub_facilities',
            'products' => [],
        ],
        [
            'id' => 'hub_patrimonio',
            'label' => 'N?cleo Patrim?nio',
            'scope' => 'hub_patrimonio',
            'products' => [],
        ],
        [
            'id' => 'hub_conhecimento',
            'label' => 'N?cleo Conhecimento',
            'scope' => 'hub_conhecimento',
            'products' => [],
        ],
        [
            'id' => 'hub_integracoes',
            'label' => 'N?cleo Integra??es',
            'scope' => 'hub_integracoes',
            'products' => [
                ['id' => 'observatorio', 'label' => 'Observat?rio Causal'],
                ['id' => 'catalogo', 'label' => 'Cat?logo'],
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
            'label' => 'N?cleo Customer Success',
            'scope' => 'hub_customer_success',
            'products' => [],
        ],
        [
            'id' => 'hub_inovacao',
            'label' => 'N?cleo Inova??o',
            'scope' => 'hub_inovacao',
            'products' => [
                ['id' => 'pipeline', 'label' => 'Pipeline'],
                ['id' => 'laboratorio', 'label' => 'Laborat?rio'],
                ['id' => 'experimentos', 'label' => 'Experimentos'],
                ['id' => 'backlog', 'label' => 'Backlog'],
                ['id' => 'analytics', 'label' => 'Analytics'],
                ['id' => 'conexoes', 'label' => 'Conex?es'],
                ['id' => 'impact', 'label' => 'Impacto'],
                ['id' => 'tendencias', 'label' => 'Tend?ncias'],
                ['id' => 'portfolio', 'label' => 'Portf?lio'],
                ['id' => 'novidades', 'label' => 'Novidades'],
            ],
        ],
        [
            'id' => 'hub_holdings',
            'label' => 'N?cleo Multi-empresa',
            'scope' => 'hub_holdings',
            'products' => [],
        ],
        [
            'id' => 'hub_seguros',
            'label' => 'N?cleo Seguros',
            'scope' => 'hub_seguros',
            'products' => [],
        ],
        [
            'id' => 'hub_saude_ocupacional',
            'label' => 'N?cleo Sa?de Ocupacional',
            'scope' => 'hub_saude_ocupacional',
            'products' => [
                ['id' => 'pcmso', 'label' => 'PCMSO'],
                ['id' => 'exames', 'label' => 'Exames ocupacionais'],
                ['id' => 'aso', 'label' => 'ASO'],
                ['id' => 'agendamentos', 'label' => 'Agendamentos'],
                ['id' => 'afastamentos', 'label' => 'Afastamentos'],
                ['id' => 'prontuario', 'label' => 'Prontu?rio ocupacional'],
            ],
        ],
        [
            'id' => 'hub_pos_operatorio',
            'label' => 'Unio Saúde',
            'scope' => 'hub_pos_operatorio',
            'products' => [
                ['id' => 'pacientes', 'label' => 'Pacientes'],
                ['id' => 'operacao', 'label' => 'Operação / recepção'],
                ['id' => 'protocolos', 'label' => 'Protocolos'],
                ['id' => 'questionarios', 'label' => 'Questionários'],
                ['id' => 'alertas', 'label' => 'Alertas clínicos'],
                ['id' => 'painel', 'label' => 'Painel de recuperação'],
                ['id' => 'portal_paciente', 'label' => 'Portal do paciente'],
                ['id' => 'relatorios', 'label' => 'Relatórios'],
                ['id' => 'configuracoes', 'label' => 'Configurações'],
            ],
        ],
        [
            'id' => 'hub_licitacoes',
            'label' => 'N?cleo Licita??es',
            'scope' => 'hub_licitacoes',
            'products' => [],
        ],
        [
            'id' => 'hub_marketing',
            'label' => 'N?cleo Marketing',
            'scope' => 'hub_marketing',
            'products' => [],
        ],
        [
            'id' => 'hub_lakehouse',
            'label' => 'N?cleo Data & Lakehouse',
            'scope' => 'hub_lakehouse',
            'products' => [],
        ],
        [
            'id' => 'hub_franquias',
            'label' => 'N?cleo Franquias & Unidades',
            'scope' => 'hub_franquias',
            'products' => [],
        ],
        [
            'id' => 'hub_seguranca_info',
            'label' => 'N?cleo Seguran?a da Informa??o',
            'scope' => 'hub_seguranca_info',
            'products' => [],
        ],
        [
            'id' => 'hub_pmo',
            'label' => 'N?cleo PMO',
            'scope' => 'hub_pmo',
            'products' => [],
        ],
        [
            'id' => 'hub_treinamento_regulatorio',
            'label' => 'N?cleo Treinamento Regulat?rio',
            'scope' => 'hub_treinamento_regulatorio',
            'products' => [],
        ],
        [
            'id' => 'hub_terceiros',
            'label' => 'N?cleo Gest?o de Terceiros',
            'scope' => 'hub_terceiros',
            'products' => [],
        ],
        [
            'id' => 'product_rh',
            'label' => 'Recursos Humanos',
            'scope' => 'product_rh',
            'products' => [
                ['id' => 'funcionarios', 'label' => 'Funcion?rios'],
                ['id' => 'admissoes', 'label' => 'Admiss?es'],
                ['id' => 'ferias', 'label' => 'F?rias'],
                ['id' => 'folha', 'label' => 'Folha'],
                ['id' => 'portal', 'label' => 'Portal do colaborador'],
                ['id' => 'recrutamento', 'label' => 'Recrutamento'],
                ['id' => 'ponto', 'label' => 'Ponto'],
                ['id' => 'comunicacao', 'label' => 'Comunica??o'],
                ['id' => 'organograma', 'label' => 'Organograma'],
                ['id' => 'auditoria', 'label' => 'Auditoria'],
                ['id' => 'workflows', 'label' => 'Workflows'],
                ['id' => 'folha_legal', 'label' => 'Folha legal'],
                ['id' => 'contabilidade', 'label' => 'Provis?es'],
                ['id' => 'esocial', 'label' => 'eSocial'],
                ['id' => 'assinatura', 'label' => 'Assinatura digital'],
                ['id' => 'analytics', 'label' => 'Analytics RH'],
            ],
        ],
        [
            'id' => 'product_pessoas',
            'label' => 'Gest?o de Pessoas',
            'scope' => 'product_pessoas',
            'products' => [
                ['id' => 'membros', 'label' => 'Membros'],
                ['id' => 'equipes', 'label' => 'Equipes'],
                ['id' => 'cargos', 'label' => 'Cargos'],
                ['id' => 'avaliacao', 'label' => 'Avalia??o'],
            ],
        ],
        [
            'id' => 'product_engenharia',
            'label' => 'Obras e Projetos',
            'scope' => 'product_engenharia',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos'],
                ['id' => 'cronograma', 'label' => 'Cronograma'],
                ['id' => 'orcamentos', 'label' => 'Or?amentos'],
                ['id' => 'equipes', 'label' => 'Equipes de Campo'],
            ],
        ],
        [
            'id' => 'product_publicidade',
            'label' => 'Marca e Comunica??o',
            'scope' => 'product_publicidade',
            'products' => [
                ['id' => 'campanhas', 'label' => 'Campanhas'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'criativos', 'label' => 'Criativos'],
                ['id' => 'metricas', 'label' => 'M?tricas'],
            ],
        ],
    ];

    /** @var array<string, array<string, array<string, string>>> scope => member_id => product_id => perfil_id */
    public const DEFAULT_GRANTS = [
        'hub_operacoes' => [
            'renata-oliveira' => ['rh' => 'GESTOR', 'pessoas' => 'GESTOR', 'engenharia' => 'GESTOR_EQUIPE'],
            'ricardo-costa' => ['rh' => 'GESTOR_EQUIPE', 'pessoas' => 'GESTOR_EQUIPE', 'engenharia' => 'SUPERVISOR'],
            'ana-ribeiro' => ['rh' => 'SUPERVISOR', 'pessoas' => 'SUPERVISOR_EQUIPE', 'engenharia' => 'MEMBRO'],
            'felipe-martins' => ['rh' => 'SUPERVISOR_EQUIPE', 'pessoas' => 'MEMBRO', 'engenharia' => 'MEMBRO'],
            'lucas-santos' => ['rh' => 'MEMBRO', 'pessoas' => 'MEMBRO'],
        ],
        'hub_talentos' => [
            'renata-oliveira' => ['banco' => 'GESTOR', 'vagas' => 'GESTOR', 'trilhas' => 'GESTOR_EQUIPE', 'mentorias' => 'GESTOR_EQUIPE'],
            'ricardo-costa' => ['banco' => 'GESTOR_EQUIPE', 'vagas' => 'SUPERVISOR', 'trilhas' => 'SUPERVISOR_EQUIPE'],
            'ana-ribeiro' => ['banco' => 'SUPERVISOR', 'vagas' => 'SUPERVISOR_EQUIPE'],
            'lucas-santos' => ['banco' => 'MEMBRO'],
        ],
        'hub_maturidade' => [
            'renata-oliveira' => ['avaliacao' => 'GESTOR', 'plano' => 'GESTOR', 'historico' => 'GESTOR_EQUIPE', 'radar' => 'GESTOR_EQUIPE'],
            'ana-ribeiro' => ['avaliacao' => 'SUPERVISOR', 'plano' => 'SUPERVISOR_EQUIPE'],
            'lucas-santos' => ['avaliacao' => 'MEMBRO'],
        ],
        'hub_recrutamento' => [
            'renata-oliveira' => ['vagas' => 'GESTOR', 'pipeline' => 'GESTOR'],
            'ricardo-costa' => ['vagas' => 'GESTOR_EQUIPE', 'pipeline' => 'SUPERVISOR'],
            'ana-ribeiro' => ['vagas' => 'SUPERVISOR', 'pipeline' => 'SUPERVISOR_EQUIPE'],
        ],
        'hub_comercial' => [
            'renata-oliveira' => [
                'leads' => 'GESTOR', 'pipeline' => 'GESTOR', 'clientes' => 'GESTOR',
                'atividades' => 'GESTOR', 'analytics' => 'GESTOR',
            ],
            'ricardo-costa' => [
                'leads' => 'GESTOR_EQUIPE', 'pipeline' => 'GESTOR_EQUIPE', 'clientes' => 'SUPERVISOR',
                'atividades' => 'GESTOR_EQUIPE', 'analytics' => 'SUPERVISOR',
            ],
            'ana-ribeiro' => [
                'leads' => 'SUPERVISOR', 'pipeline' => 'SUPERVISOR_EQUIPE', 'clientes' => 'SUPERVISOR',
                'atividades' => 'SUPERVISOR', 'analytics' => 'MEMBRO',
            ],
        ],
        'hub_admin' => [
            'renata-oliveira' => ['usuarios' => 'GESTOR', 'empresas' => 'GESTOR', 'configuracoes' => 'GESTOR_EQUIPE'],
        ],
        'product_rh' => [
            'renata-oliveira' => [
                'funcionarios' => 'GESTOR', 'admissoes' => 'GESTOR', 'ferias' => 'GESTOR_EQUIPE', 'folha' => 'GESTOR',
                'portal' => 'GESTOR_EQUIPE', 'recrutamento' => 'GESTOR', 'ponto' => 'GESTOR_EQUIPE',
                'comunicacao' => 'GESTOR', 'organograma' => 'GESTOR', 'auditoria' => 'GESTOR',
                'workflows' => 'GESTOR', 'folha_legal' => 'GESTOR', 'contabilidade' => 'GESTOR',
                'esocial' => 'GESTOR', 'assinatura' => 'GESTOR', 'analytics' => 'GESTOR',
            ],
            'ana-ribeiro' => [
                'funcionarios' => 'SUPERVISOR', 'ferias' => 'SUPERVISOR_EQUIPE', 'folha' => 'SUPERVISOR',
                'portal' => 'MEMBRO', 'recrutamento' => 'SUPERVISOR', 'ponto' => 'SUPERVISOR_EQUIPE',
                'comunicacao' => 'SUPERVISOR', 'organograma' => 'SUPERVISOR', 'auditoria' => 'SUPERVISOR',
                'workflows' => 'SUPERVISOR_EQUIPE', 'folha_legal' => 'SUPERVISOR', 'contabilidade' => 'SUPERVISOR',
                'esocial' => 'SUPERVISOR_EQUIPE', 'assinatura' => 'SUPERVISOR_EQUIPE', 'analytics' => 'SUPERVISOR',
            ],
            'lucas-santos' => [
                'funcionarios' => 'MEMBRO', 'portal' => 'MEMBRO', 'ponto' => 'MEMBRO', 'comunicacao' => 'MEMBRO',
                'organograma' => 'MEMBRO', 'analytics' => 'MEMBRO',
            ],
        ],
        'product_pessoas' => [
            'renata-oliveira' => ['membros' => 'GESTOR', 'equipes' => 'GESTOR', 'cargos' => 'GESTOR_EQUIPE', 'avaliacao' => 'GESTOR_EQUIPE'],
            'ricardo-costa' => ['membros' => 'GESTOR_EQUIPE', 'equipes' => 'SUPERVISOR'],
            'ana-ribeiro' => ['membros' => 'SUPERVISOR_EQUIPE', 'equipes' => 'SUPERVISOR', 'cargos' => 'SUPERVISOR_EQUIPE', 'avaliacao' => 'SUPERVISOR'],
            'felipe-martins' => ['membros' => 'MEMBRO', 'equipes' => 'SUPERVISOR_EQUIPE'],
            'lucas-santos' => ['membros' => 'MEMBRO'],
        ],
        'product_engenharia' => [
            'renata-oliveira' => ['projetos' => 'GESTOR', 'cronograma' => 'GESTOR_EQUIPE', 'orcamentos' => 'GESTOR', 'equipes' => 'SUPERVISOR'],
            'ana-ribeiro' => ['projetos' => 'SUPERVISOR_EQUIPE', 'equipes' => 'SUPERVISOR_EQUIPE'],
        ],
        'product_publicidade' => [
            'renata-oliveira' => ['campanhas' => 'GESTOR', 'clientes' => 'GESTOR', 'criativos' => 'GESTOR_EQUIPE', 'metricas' => 'GESTOR_EQUIPE'],
        ],
        'product_core' => [
            'renata-oliveira' => ['projetos' => 'GESTOR', 'metas' => 'GESTOR'],
            'ricardo-costa' => ['projetos' => 'GESTOR_EQUIPE', 'metas' => 'GESTOR_EQUIPE'],
            'ana-ribeiro' => ['projetos' => 'SUPERVISOR', 'metas' => 'SUPERVISOR'],
        ],
        'hub_ti' => [
            'renata-oliveira' => [
                'chamados' => 'GESTOR', 'catalogo' => 'GESTOR', 'kb' => 'GESTOR', 'problemas' => 'GESTOR',
                'meus_chamados' => 'GESTOR', 'sla' => 'GESTOR', 'manutencoes' => 'GESTOR', 'ativos' => 'GESTOR',
                'licencas' => 'GESTOR', 'integracoes' => 'GESTOR', 'cortex' => 'GESTOR', 'analytics' => 'GESTOR',
                'novidades' => 'GESTOR',
            ],
            'ricardo-costa' => [
                'chamados' => 'GESTOR_EQUIPE', 'catalogo' => 'GESTOR_EQUIPE', 'kb' => 'GESTOR_EQUIPE',
                'problemas' => 'GESTOR_EQUIPE', 'meus_chamados' => 'GESTOR_EQUIPE', 'sla' => 'GESTOR_EQUIPE',
                'manutencoes' => 'GESTOR_EQUIPE', 'ativos' => 'GESTOR_EQUIPE', 'licencas' => 'GESTOR_EQUIPE',
                'integracoes' => 'GESTOR_EQUIPE', 'cortex' => 'GESTOR_EQUIPE', 'analytics' => 'GESTOR_EQUIPE',
                'novidades' => 'GESTOR_EQUIPE',
            ],
            'ana-ribeiro' => [
                'chamados' => 'SUPERVISOR', 'catalogo' => 'SUPERVISOR', 'kb' => 'SUPERVISOR_EQUIPE',
                'problemas' => 'SUPERVISOR', 'meus_chamados' => 'MEMBRO', 'sla' => 'SUPERVISOR',
                'manutencoes' => 'SUPERVISOR', 'ativos' => 'SUPERVISOR', 'licencas' => 'SUPERVISOR',
                'integracoes' => 'SUPERVISOR', 'cortex' => 'SUPERVISOR', 'analytics' => 'SUPERVISOR',
                'novidades' => 'SUPERVISOR_EQUIPE',
            ],
            'felipe-martins' => [
                'chamados' => 'SUPERVISOR_EQUIPE', 'catalogo' => 'SUPERVISOR_EQUIPE', 'kb' => 'SUPERVISOR_EQUIPE',
                'problemas' => 'SUPERVISOR_EQUIPE', 'meus_chamados' => 'MEMBRO', 'sla' => 'SUPERVISOR_EQUIPE',
                'manutencoes' => 'SUPERVISOR_EQUIPE', 'ativos' => 'SUPERVISOR_EQUIPE', 'licencas' => 'SUPERVISOR_EQUIPE',
                'integracoes' => 'SUPERVISOR_EQUIPE', 'cortex' => 'SUPERVISOR_EQUIPE', 'analytics' => 'SUPERVISOR_EQUIPE',
                'novidades' => 'MEMBRO',
            ],
            'lucas-santos' => [
                'catalogo' => 'MEMBRO', 'meus_chamados' => 'MEMBRO', 'novidades' => 'MEMBRO',
            ],
        ],
        'hub_pos_operatorio' => [
            'camila-souza' => [
                'pacientes' => ClinicStaffRole::RECEPCAO,
                'operacao' => ClinicStaffRole::RECEPCAO,
            ],
            'beatriz-nunes' => [
                'questionarios' => ClinicStaffRole::ENFERMAGEM,
                'painel' => ClinicStaffRole::ENFERMAGEM,
                'portal_paciente' => ClinicStaffRole::ENFERMAGEM,
                'pacientes' => ClinicStaffRole::ENFERMAGEM,
            ],
            'andre-melo' => [
                'alertas' => ClinicStaffRole::MEDICO,
                'pacientes' => ClinicStaffRole::MEDICO,
                'protocolos' => ClinicStaffRole::MEDICO,
                'painel' => ClinicStaffRole::MEDICO,
            ],
            'helena-castro' => [
                'relatorios' => ClinicStaffRole::COORDENACAO,
                'configuracoes' => ClinicStaffRole::COORDENACAO,
                'pacientes' => ClinicStaffRole::COORDENACAO,
            ],
        ],
        'hub_saude_ocupacional' => [
            'renata-oliveira' => [
                'pcmso' => 'GESTOR', 'exames' => 'GESTOR', 'aso' => 'GESTOR',
                'agendamentos' => 'GESTOR', 'afastamentos' => 'GESTOR', 'prontuario' => 'GESTOR',
            ],
            'ricardo-costa' => [
                'pcmso' => 'GESTOR_EQUIPE', 'exames' => 'GESTOR_EQUIPE', 'aso' => 'GESTOR_EQUIPE',
                'agendamentos' => 'GESTOR_EQUIPE', 'afastamentos' => 'GESTOR_EQUIPE', 'prontuario' => 'GESTOR_EQUIPE',
            ],
            'ana-ribeiro' => [
                'pcmso' => 'SUPERVISOR', 'exames' => 'SUPERVISOR', 'aso' => 'SUPERVISOR',
                'agendamentos' => 'SUPERVISOR_EQUIPE', 'afastamentos' => 'SUPERVISOR', 'prontuario' => 'SUPERVISOR_EQUIPE',
            ],
            'felipe-martins' => [
                'exames' => 'SUPERVISOR_EQUIPE', 'aso' => 'SUPERVISOR_EQUIPE',
                'agendamentos' => 'SUPERVISOR_EQUIPE', 'afastamentos' => 'SUPERVISOR_EQUIPE',
            ],
            'lucas-santos' => [
                'agendamentos' => 'MEMBRO', 'aso' => 'MEMBRO',
            ],
        ],
        'hub_integracoes' => [
            'renata-oliveira' => [
                'observatorio' => 'GESTOR', 'catalogo' => 'GESTOR', 'conectores' => 'GESTOR', 'webhooks' => 'GESTOR',
                'mapeamentos' => 'GESTOR', 'api_keys' => 'GESTOR', 'logs' => 'GESTOR', 'playbooks' => 'GESTOR',
            ],
            'ricardo-costa' => [
                'observatorio' => 'GESTOR_EQUIPE', 'catalogo' => 'GESTOR_EQUIPE', 'conectores' => 'GESTOR_EQUIPE', 'webhooks' => 'GESTOR_EQUIPE',
                'mapeamentos' => 'GESTOR_EQUIPE', 'api_keys' => 'GESTOR_EQUIPE', 'logs' => 'SUPERVISOR', 'playbooks' => 'GESTOR_EQUIPE',
            ],
            'ana-ribeiro' => [
                'observatorio' => 'SUPERVISOR', 'catalogo' => 'SUPERVISOR', 'conectores' => 'SUPERVISOR', 'webhooks' => 'SUPERVISOR',
                'mapeamentos' => 'SUPERVISOR_EQUIPE', 'api_keys' => 'SUPERVISOR', 'logs' => 'SUPERVISOR', 'playbooks' => 'SUPERVISOR',
            ],
            'felipe-martins' => [
                'observatorio' => 'SUPERVISOR_EQUIPE', 'catalogo' => 'SUPERVISOR_EQUIPE', 'conectores' => 'SUPERVISOR_EQUIPE', 'webhooks' => 'SUPERVISOR_EQUIPE',
                'logs' => 'SUPERVISOR_EQUIPE', 'playbooks' => 'MEMBRO',
            ],
            'lucas-santos' => [
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
        $profiles = self::assignableProfilesForScope($scope);
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
     * Persiste grants granulares de um membro (substitui todos os registros do usu?rio).
     *
     * @param array<string, string> $grantsMap keys "scope:productId" => perfil_id (vazio = sem acesso)
     *
     * @return int n?mero de grants gravados
     */
    public function saveMemberGrants(string $memberId, array $grantsMap, User $editor): int
    {
        if (!$this->canEditorSaveGrants($editor, $grantsMap)) {
            throw new AccessDeniedException('Sem permiss?o para alterar grants.');
        }

        $empresa = $this->workspace->getActiveEmpresa($editor) ?? $editor->getEmpresa();
        $target = $this->resolveUserForMemberId($memberId, $empresa);
        if (!$target) {
            throw new \InvalidArgumentException('Membro n?o encontrado nesta empresa.');
        }

        if (\in_array($target->getPerfil(), ['TENANT', 'PLATFORM_OWNER'], true)) {
            throw new \InvalidArgumentException('Permiss?es de contas globais da plataforma n?o s?o edit?veis.');
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

            $allowedProfiles = array_column(self::assignableProfilesForScope($scope), 'id');
            if (!\in_array($perfilGrant, $allowedProfiles, true)) {
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
            return ['label' => '?', 'class' => 'none', 'description' => 'Escopo n?o encontrado.'];
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
                'description' => 'Sem permiss?o neste escopo ? produtos desta aba bloqueados.',
            ];
        }

        $unique = array_values(array_unique($values));
        if (\count($unique) === 1) {
            foreach (self::assignableProfilesForScope($scope) as $profile) {
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
        return DevSeedEmails::memberSlotId($email);
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
     * Membros da empresa para busca global e painel de permiss?es.
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

        $equipe = '?';
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
                ClinicStaffRole::RECEPCAO,
                ClinicStaffRole::ENFERMAGEM,
                ClinicStaffRole::MEDICO,
                ClinicStaffRole::COORDENACAO => ClinicStaffRole::label($perfil),
                default => $perfil,
            },
            'perfil_class' => match ($perfil) {
                'MEMBRO' => 'membro',
                'SUPERVISOR_EQUIPE' => 'supervisor-equipe',
                'SUPERVISOR' => 'supervisor',
                'GESTOR_EQUIPE' => 'gestor-equipe',
                'GESTOR' => 'gestor',
                ClinicStaffRole::RECEPCAO => 'membro',
                ClinicStaffRole::ENFERMAGEM => 'supervisor-equipe',
                ClinicStaffRole::MEDICO => 'supervisor',
                ClinicStaffRole::COORDENACAO => 'gestor',
                default => 'default',
            },
            'ficha_id' => $fichaId,
            'user_id' => $userId,
        ];
    }
}
