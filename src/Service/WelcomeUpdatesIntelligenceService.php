<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\RhFerias;
use App\Entity\User;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\RhComunicadoRepository;
use App\Repository\RhFeriasRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Service\Rh\RhModuleStatsService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Timeline de atualizações da boas-vindas — changelog + pulso dinâmico da plataforma.
 */
final class WelcomeUpdatesIntelligenceService
{
    private const TZ = 'America/Sao_Paulo';
    public const MAX_ITEMS = 4;

    /** @var list<array{id: string, tag: string, title: string, summary: string, date: string, type: string, items: list<string>}> */
    private const CHANGELOG = [
        [
            'id' => 'welcome-2026-05',
            'tag' => 'Experiência',
            'title' => 'Central de boas-vindas renovada',
            'summary' => 'Hero personalizado, Vitória em destaque e seções configuráveis por perfil.',
            'date' => '2026-05-27',
            'type' => 'feature',
            'items' => [
                'Painel de notícias e atualizações da plataforma',
                'Resumo profissional da área de trabalho',
                'Personalização de seções no navegador',
            ],
        ],
        [
            'id' => 'hubs-roadmap',
            'tag' => 'Hubs',
            'title' => 'Novos hubs no catálogo',
            'summary' => 'Comercial, Benefícios, Academy, Financeiro e mais áreas já acessíveis na navegação.',
            'date' => '2026-05-20',
            'type' => 'feature',
            'items' => [
                'Landing dedicada por hub em desenvolvimento',
                'Apps launcher com acesso rápido',
                'Permissões por escopo de hub',
            ],
        ],
        [
            'id' => 'pessoas-membros',
            'tag' => 'Operações',
            'title' => 'Gestão de Pessoas ampliada',
            'summary' => 'Membros, equipes, organograma e avaliações no Hub Operações.',
            'date' => '2026-05-12',
            'type' => 'improvement',
            'items' => [
                'Ficha técnica com fotos',
                'Organograma interativo',
                'Integração com módulo RH',
            ],
        ],
        [
            'id' => 'security-grants',
            'tag' => 'Segurança',
            'title' => 'Matriz de permissões granular',
            'summary' => 'Controle fino por produto e hub para perfis gestor e tenant.',
            'date' => '2026-05-05',
            'type' => 'improvement',
            'items' => [
                'Grants por escopo no banco',
                'Fallback por perfil legado',
            ],
        ],
    ];

    public function __construct(
        private NavigationService $navigation,
        private WelcomeService $welcome,
        private DashboardStatsService $dashboardStats,
        private RhModuleStatsService $rhStats,
        private FuncionarioRepository $funcionarioRepo,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhFeriasRepository $feriasRepo,
        private RhComunicadoRepository $comunicadoRepo,
        private DevProjetoRepository $projetoRepo,
        private DevTarefaRepository $tarefaRepo,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, dynamic_count: int}
     */
    public function buildPayload(User $user, ?Empresa $empresa, string $layout, int $empresasCount = 1): array
    {
        $dynamic = $this->buildDynamicUpdates($user, $empresa, $layout, $empresasCount);
        $static = $this->formatChangelog();

        $merged = array_merge($dynamic, $static);
        usort($merged, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        $items = \array_slice($merged, 0, self::MAX_ITEMS);
        foreach ($items as &$item) {
            $item['date_label'] = $this->formatDateLabel($item['date']);
        }
        unset($item);

        return [
            'items' => $items,
            'dynamic_count' => \count($dynamic),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDynamicUpdates(User $user, ?Empresa $empresa, string $layout, int $empresasCount): array
    {
        $today = new DateTimeImmutable('now', new DateTimeZone(self::TZ));
        $date = $today->format('Y-m-d');
        $updates = [];

        if ($empresa !== null) {
            $bullets = [];
            $headcount = (int) $this->funcionarioRepo->count(['empresa' => $empresa->getId()]);
            if ($headcount > 0) {
                $bullets[] = sprintf('%s colaborador(es) ativos na área de trabalho', number_format($headcount, 0, ',', '.'));
            }

            if ($this->navigation->showModuloRh($user)) {
                foreach ($this->rhStats->hubModules($empresa) as $module) {
                    $count = (int) ($module['count'] ?? 0);
                    if ($count <= 0) {
                        continue;
                    }
                    $label = (string) ($module['label'] ?? $module['title'] ?? 'módulo');
                    $bullets[] = sprintf('%s: %s %s', $module['title'] ?? 'RH', number_format($count, 0, ',', '.'), $label);
                    if (\count($bullets) >= 4) {
                        break;
                    }
                }

                $admissoes = $this->onboardingRepo->countOpenByEmpresa($empresa);
                $ferias = $this->feriasRepo->countByStatus($empresa, RhFerias::STATUS_SOLICITADA);
                $comunicados = $this->comunicadoRepo->countAtivosByEmpresa($empresa);
                if ($admissoes + $ferias + $comunicados > 0 && $bullets !== []) {
                    $updates[] = $this->update(
                        id: 'pulse-rh-' . $empresa->getId() . '-' . $date,
                        tag: 'RH · Ao vivo',
                        title: 'Operação de pessoas em movimento',
                        summary: 'Indicadores reais do Hub Operações e Talentos nesta área de trabalho.',
                        date: $date,
                        type: 'pulse',
                        items: \array_slice($bullets, 0, 4),
                    );
                }
            }

            if ($this->navigation->showProjetosMetas($user)) {
                $projetos = $this->projetoRepo->countEmAndamento($empresa);
                $tarefas = (int) $this->tarefaRepo->count(['empresa' => $empresa->getId()]);
                if ($projetos > 0 || $tarefas > 0) {
                    $updates[] = $this->update(
                        id: 'pulse-projetos-' . $empresa->getId() . '-' . $date,
                        tag: 'Entregas',
                        title: 'Portfólio de projetos monitorado',
                        summary: sprintf(
                            '%s projeto(s) ativo(s) e %s tarefa(s) registrada(s).',
                            number_format($projetos, 0, ',', '.'),
                            number_format($tarefas, 0, ',', '.'),
                        ),
                        date: $date,
                        type: 'pulse',
                        items: [
                            'Kanban e metas refletem o backlog atual',
                            'Priorize projetos com maior impacto operacional',
                            'Revise responsáveis e prazos semanalmente',
                        ],
                    );
                }
            }
        }

        $novidades = $this->welcome->getNovidadesForUser($user);
        if ($novidades !== []) {
            $updates[] = $this->update(
                id: 'pulse-novidades-' . $date,
                tag: 'Novidades',
                title: sprintf('%d recurso(s) novo(s) no seu perfil', \count($novidades)),
                summary: 'Hubs e módulos liberados recentemente para a sua operação.',
                date: $date,
                type: 'feature',
                items: array_map(
                    static fn (array $n): string => (string) ($n['title'] ?? 'Recurso'),
                    \array_slice($novidades, 0, 4),
                ),
            );
        }

        $kpis = $this->dashboardStats->getKpis($user, $empresa, $layout, $empresasCount);
        if ($kpis !== [] && $empresa === null && $this->navigation->isTenant($user)) {
            $updates[] = $this->update(
                id: 'pulse-plataforma-' . $date,
                tag: 'Plataforma',
                title: 'Visão global da operação',
                summary: 'Consolidado multi-empresa com dados reais do tenant.',
                date: $date,
                type: 'pulse',
                items: array_map(
                    static fn (array $kpi): string => sprintf('%s: %s', $kpi['label'], number_format((int) $kpi['value'], 0, ',', '.')),
                    \array_slice($kpis, 0, 4),
                ),
            );
        }

        return $updates;
    }

    /**
     * @param list<string> $items
     *
     * @return array<string, mixed>
     */
    private function update(
        string $id,
        string $tag,
        string $title,
        string $summary,
        string $date,
        string $type,
        array $items,
    ): array {
        return [
            'id' => $id,
            'tag' => $tag,
            'title' => $title,
            'summary' => $summary,
            'date' => $date,
            'type' => $type,
            'items' => $items,
            'is_dynamic' => true,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function formatChangelog(): array
    {
        $items = [];
        foreach (self::CHANGELOG as $entry) {
            $items[] = array_merge($entry, ['is_dynamic' => false]);
        }

        return $items;
    }

    private function formatDateLabel(string $isoDate): string
    {
        try {
            return (new DateTimeImmutable($isoDate, new DateTimeZone(self::TZ)))->format('d/m/Y');
        } catch (\Exception) {
            return $isoDate;
        }
    }
}
