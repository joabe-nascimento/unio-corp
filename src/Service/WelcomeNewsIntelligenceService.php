<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\RhFerias;
use App\Entity\User;
use App\Repository\DepartamentoRepository;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use App\Repository\EmpresaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\RhComunicadoRepository;
use App\Repository\RhFeriasRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Artigos gerados a partir de dados reais da área de trabalho (inteligência operacional).
 */
final class WelcomeNewsIntelligenceService
{
    private const TZ = 'America/Sao_Paulo';

    public function __construct(
        private NavigationService $navigation,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private RhFeriasRepository $feriasRepo,
        private RhComunicadoRepository $comunicadoRepo,
        private DevProjetoRepository $projetoRepo,
        private DevTarefaRepository $tarefaRepo,
        private FuncionarioRepository $funcionarioRepo,
        private DepartamentoRepository $departamentoRepo,
        private EmpresaRepository $empresaRepo,
        private UserRepository $userRepo,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buildInsights(User $user, ?Empresa $empresa, string $layout): array
    {
        if ($empresa === null) {
            return $this->buildTenantInsights($user, $layout);
        }

        $today = new DateTimeImmutable('now', new DateTimeZone(self::TZ));
        $insights = [];

        if ($this->navigation->showModuloRh($user)) {
            $openAdmissions = $this->onboardingRepo->countOpenByEmpresa($empresa);
            if ($openAdmissions > 0) {
                $insights[] = $this->insight(
                    id: 'insight-admissoes-' . $empresa->getId(),
                    slug: 'insight-admissoes-' . $empresa->getId() . '-' . $today->format('Y-m'),
                    category: 'Alerta operacional',
                    title: sprintf('%d admissão(ões) aguardando ação', $openAdmissions),
                    summary: 'Processos de onboarding em aberto nesta área de trabalho — priorize etapas pendentes no RH.',
                    icon: 'fa-user-plus',
                    relatedRoute: 'app_rh_admissoes',
                    body: [
                        sprintf('Identificamos %s processo(s) de admissão em andamento ou pendente(s) para %s.', number_format($openAdmissions, 0, ',', '.'), $empresa->getNome() ?? 'sua empresa'),
                        'Admissões paradas elevam risco de atraso em documentação, integração e folha. Revise responsáveis e prazos de cada etapa no módulo de RH.',
                        'Sugestão: comece pelos processos mais antigos e valide documentos obrigatórios antes de concluir a integração do colaborador.',
                    ],
                    layouts: ['tenant', 'gestor', 'supervisor', 'gestor_equipe', 'supervisor_equipe'],
                );
            }
        }

        if ($this->navigation->showProjetosMetas($user)) {
            $activeProjects = $this->projetoRepo->countEmAndamento($empresa);
            $totalTasks = (int) $this->tarefaRepo->count(['empresa' => $empresa]);
            if ($activeProjects > 0) {
                $insights[] = $this->insight(
                    id: 'insight-projetos-' . $empresa->getId(),
                    slug: 'insight-projetos-' . $empresa->getId() . '-' . $today->format('Y-m'),
                    category: 'Entregas',
                    title: sprintf('%d projeto(s) ativo(s) na operação', $activeProjects),
                    summary: $totalTasks > 0
                        ? sprintf('Portfólio com %d tarefa(s) registrada(s) — acompanhe o quadro de metas.', $totalTasks)
                        : 'Há projetos em andamento; organize entregas no módulo de projetos.',
                    icon: 'fa-diagram-project',
                    relatedRoute: 'app_core_projetos',
                    body: [
                        sprintf('Sua área possui %s projeto(s) com status em andamento.', number_format($activeProjects, 0, ',', '.')),
                        $totalTasks > 0
                            ? sprintf('O backlog soma %s tarefa(s) vinculadas — use o Kanban para redistribuir carga entre a equipe.', number_format($totalTasks, 0, ',', '.'))
                            : 'Cadastre tarefas nos projetos ativos para ganhar visibilidade de prazo e responsáveis.',
                        'Revisão semanal do portfólio reduz retrabalho e melhora previsibilidade de entregas.',
                    ],
                    layouts: ['tenant', 'gestor', 'supervisor', 'membro', 'gestor_equipe', 'supervisor_equipe'],
                );
            }
        }

        $headcount = (int) $this->funcionarioRepo->count(['empresa' => $empresa]);
        if ($headcount > 0 && $headcount <= 5) {
            $insights[] = $this->insight(
                id: 'insight-quadro-compacto-' . $empresa->getId(),
                slug: 'insight-quadro-' . $empresa->getId() . '-' . $today->format('Y-m'),
                category: 'People',
                title: 'Quadro enxuto — momento de estruturar',
                summary: sprintf('%d colaborador(es) ativos: ideal para definir processos e organograma.', $headcount),
                icon: 'fa-users',
                relatedRoute: 'app_pessoas_membros',
                body: [
                    sprintf('Com %s colaborador(es) registrado(s), cada admissão e movimentação impacta fortemente a operação.', number_format($headcount, 0, ',', '.')),
                    'Aproveite para cadastrar departamentos, equipes e avaliações — a base fica pronta antes da expansão do quadro.',
                    'O Núcleo de Operações concentra pessoas, RH e organograma para acelerar essa estruturação.',
                ],
                layouts: ['tenant', 'gestor'],
            );
        }

        return $this->filterByLayout($insights, $layout);
    }

    /**
     * Varredura extra da plataforma — gera leituras diárias quando o usuário zerou não lidas.
     *
     * @return list<array<string, mixed>>
     */
    public function buildDiscoveryScan(User $user, ?Empresa $empresa, string $layout): array
    {
        $today = new DateTimeImmutable('now', new DateTimeZone(self::TZ));
        $dayKey = $today->format('Y-m-d');
        $items = [];

        if ($empresa !== null) {
            $empresaId = (int) $empresa->getId();
            $empresaNome = $empresa->getNome() ?? 'sua empresa';

            if ($this->navigation->showModuloRh($user)) {
                $feriasPendentes = $this->feriasRepo->countByStatus($empresa, RhFerias::STATUS_SOLICITADA);
                if ($feriasPendentes > 0) {
                    $items[] = $this->insight(
                        id: 'discovery-ferias-' . $empresaId,
                        slug: 'discovery-ferias-' . $empresaId . '-' . $dayKey,
                        category: 'RH · Férias',
                        title: sprintf('%d solicitação(ões) de férias na fila', $feriasPendentes),
                        summary: 'Revise aprovações pendentes para evitar conflitos de escala e folga.',
                        icon: 'fa-umbrella-beach',
                        relatedRoute: 'app_rh_ferias',
                        body: [
                            sprintf('Há %s pedido(s) aguardando decisão em %s.', number_format($feriasPendentes, 0, ',', '.'), $empresaNome),
                            'Férias não tratadas a tempo impactam planejamento de equipe, cobertura operacional e clima.',
                            'Priorize solicitações mais antigas e valide saldo antes de aprovar.',
                        ],
                        layouts: ['tenant', 'gestor', 'supervisor', 'gestor_equipe', 'supervisor_equipe'],
                    );
                }

                $comunicados = $this->comunicadoRepo->countAtivosByEmpresa($empresa);
                if ($comunicados > 0) {
                    $items[] = $this->insight(
                        id: 'discovery-comunicados-' . $empresaId,
                        slug: 'discovery-comunicados-' . $empresaId . '-' . $dayKey,
                        category: 'Comunicação interna',
                        title: sprintf('%d comunicado(s) ativo(s) no portal', $comunicados),
                        summary: 'Confira se a equipe está recebendo avisos importantes da operação.',
                        icon: 'fa-bullhorn',
                        relatedRoute: 'app_rh_comunicacao',
                        body: [
                            sprintf('%s comunicado(s) ativo(s) em %s aguardam leitura ou reforço.', number_format($comunicados, 0, ',', '.'), $empresaNome),
                            'Comunicados desatualizados geram ruído; encerre os expirados e destaque o que é prioritário.',
                            'Use o módulo de Comunicação para acompanhar engajamento e reenviar o que for crítico.',
                        ],
                        layouts: ['tenant', 'gestor', 'supervisor', 'membro', 'gestor_equipe', 'supervisor_equipe'],
                    );
                }

                $offboarding = $this->offboardingRepo->countOpenByEmpresa($empresa);
                if ($offboarding > 0) {
                    $items[] = $this->insight(
                        id: 'discovery-offboarding-' . $empresaId,
                        slug: 'discovery-offboarding-' . $empresaId . '-' . $dayKey,
                        category: 'Offboarding',
                        title: sprintf('%d desligamento(s) em andamento', $offboarding),
                        summary: 'Garanta checklist de acesso, documentos e entrevista de saída.',
                        icon: 'fa-door-open',
                        relatedRoute: 'app_rh_demissoes',
                        body: [
                            sprintf('Identificamos %s processo(s) de offboarding aberto(s) em %s.', number_format($offboarding, 0, ',', '.'), $empresaNome),
                            'Desligamentos incompletos elevam risco de acesso indevido e passivo trabalhista.',
                            'Revise cada etapa no RH antes de encerrar a conta do colaborador.',
                        ],
                        layouts: ['tenant', 'gestor', 'supervisor'],
                    );
                }
            }

            $departamentos = (int) $this->departamentoRepo->count(['empresa' => $empresaId]);
            if ($departamentos === 0 && (int) $this->funcionarioRepo->count(['empresa' => $empresaId]) > 0) {
                $items[] = $this->insight(
                    id: 'discovery-organograma-' . $empresaId,
                    slug: 'discovery-organograma-' . $empresaId . '-' . $dayKey,
                    category: 'Estrutura',
                    title: 'Organograma ainda não estruturado',
                    summary: 'Cadastre departamentos para destravar organograma e relatórios por área.',
                    icon: 'fa-sitemap',
                    relatedRoute: 'app_pessoas_membros',
                    body: [
                        'Colaboradores já estão registrados, mas não há departamentos vinculados.',
                        'Sem estrutura, relatórios de headcount e permissões por área ficam limitados.',
                        'Comece criando os departamentos principais e associe gestores responsáveis.',
                    ],
                    layouts: ['tenant', 'gestor'],
                );
            }
        }

        if ($user->hasPlatformAccess()) {
            $empresasAtivas = (int) $this->empresaRepo->count(['ativo' => true]);
            if ($empresasAtivas > 1) {
                $items[] = $this->insight(
                    id: 'discovery-empresas',
                    slug: 'discovery-empresas-' . $dayKey,
                    category: 'Multi-empresa',
                    title: sprintf('%d empresas ativas na plataforma', $empresasAtivas),
                    summary: 'Alterne workspaces e valide grants antes de liberar novos núcleos.',
                    icon: 'fa-building',
                    relatedRoute: 'app_admin_empresas',
                    body: [
                        sprintf('A plataforma opera com %s empresa(s) ativa(s) neste momento.', number_format($empresasAtivas, 0, ',', '.')),
                        'Revise logos, setores e usuários vinculados antes de expandir módulos.',
                        'Use a seleção de workspace para auditar cada operação separadamente.',
                    ],
                    layouts: ['tenant'],
                );
            }
        }

        foreach ($items as &$item) {
            $item['is_discovery'] = true;
        }
        unset($item);

        return $this->filterByLayout($items, $layout);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTenantInsights(User $user, string $layout): array
    {
        if (!$user->hasPlatformAccess()) {
            return [];
        }

        $today = new DateTimeImmutable('now', new DateTimeZone(self::TZ));
        $totalUsers = (int) $this->userRepo->count([]);

        if ($totalUsers < 2) {
            return [];
        }

        return $this->filterByLayout([
            $this->insight(
                id: 'insight-usuarios-plataforma',
                slug: 'insight-usuarios-' . $today->format('Y-m'),
                category: 'Governança',
                title: sprintf('%d usuários na plataforma', $totalUsers),
                summary: 'Revise perfis e acessos para manter segurança conforme a operação cresce.',
                icon: 'fa-user-shield',
                relatedRoute: 'app_admin_usuarios',
                body: [
                    sprintf('Há %s conta(s) de acesso cadastrada(s) na plataforma.', number_format($totalUsers, 0, ',', '.')),
                    'Recomendamos auditoria mensal de perfis elevados (tenant e gestores) e desativação de contas sem uso.',
                    'A matriz de permissões por hub reduz risco ao liberar novos módulos para as empresas.',
                ],
                layouts: ['tenant'],
            ),
        ], $layout);
    }

    /**
     * @param list<string> $layouts
     *
     * @return array<string, mixed>
     */
    private function insight(
        string $id,
        string $slug,
        string $category,
        string $title,
        string $summary,
        string $icon,
        string $relatedRoute,
        array $body,
        array $layouts,
    ): array {
        $today = new DateTimeImmutable('now', new DateTimeZone(self::TZ));

        return [
            'id' => $id,
            'slug' => $slug,
            'category' => $category,
            'title' => $title,
            'summary' => $summary,
            'icon' => $icon,
            'published_at' => $today->format('Y-m-d'),
            'published_ts' => $today->getTimestamp(),
            'read_min' => max(2, min(4, (int) ceil(str_word_count(implode(' ', $body)) / 180))),
            'is_live' => false,
            'is_insight' => true,
            'related_route' => $relatedRoute,
            'layouts' => $layouts,
            'body' => $body,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function filterByLayout(array $items, string $layout): array
    {
        return array_values(array_filter($items, static function (array $item) use ($layout): bool {
            $layouts = $item['layouts'] ?? [];

            return $layouts === [] || \in_array($layout, $layouts, true)
                || ($layout === 'platform_owner' && \in_array('tenant', $layouts, true));
        }));
    }
}
