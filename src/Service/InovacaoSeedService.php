<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\InovConexao;
use App\Entity\InovDecisao;
use App\Entity\InovIdeia;
use App\Entity\InovImpactEntry;
use App\Entity\InovNovidade;
use App\Entity\InovTendencia;
use App\Entity\User;
use App\Repository\InovIdeiaRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Popula dados demo do Núcleo Inovação quando a empresa ainda não tem ideias.
 */
final class InovacaoSeedService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InovIdeiaRepository $ideiaRepo,
    ) {}

    public function seedDemoData(Empresa $empresa, User $autor): bool
    {
        if ($this->ideiaRepo->countByEmpresa($empresa) > 0) {
            return false;
        }

        $ideiasByCodigo = [];
        foreach ($this->pipelineSeed() as $row) {
            $ideiasByCodigo[$row['codigo']] = $this->persistIdeia($empresa, $autor, $row);
        }

        foreach ($this->decisionSeed() as $row) {
            $this->persistDecisao($empresa, $autor, $row);
        }

        foreach ($this->conexaoSeed() as $row) {
            $this->persistConexao($empresa, $autor, $row);
        }

        foreach ($this->impactSeed() as $row) {
            $this->persistImpact($empresa, $ideiasByCodigo, $row);
        }

        foreach ($this->tendenciaSeed() as $i => $row) {
            $this->persistTendencia($empresa, $row, $i);
        }

        foreach ($this->novidadeSeed() as $i => $row) {
            $this->persistNovidade($empresa, $autor, $row, $i);
        }

        $this->em->flush();

        return true;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function persistIdeia(Empresa $empresa, User $autor, array $row): InovIdeia
    {
        $ideia = new InovIdeia();
        $ideia->setEmpresa($empresa);
        $ideia->setAutor($autor);
        $ideia->setCodigo($row['codigo']);
        $ideia->setTitulo($row['title']);
        $ideia->setResumo($row['summary']);
        $ideia->setEstagio($row['stage']);
        $ideia->setOwnerNome($row['owner']);
        $ideia->setTags($row['tags']);
        $ideia->setProgresso($row['progress']);
        $ideia->setImpacto($row['impact'] ?? 50);
        $ideia->setEsforco($row['effort'] ?? 50);
        $ideia->setVotos($row['votes'] ?? 0);
        if (isset($row['metric'])) {
            $ideia->setMetrica($row['metric']);
        }
        if (isset($row['rigor'])) {
            $ideia->setRigor($row['rigor']);
        }

        $days = (int) ($row['days'] ?? 0);
        if ($days > 0) {
            $criado = (new \DateTimeImmutable())->modify('-' . $days . ' days');
            $ref = new \ReflectionClass($ideia);
            $prop = $ref->getProperty('criadoEm');
            $prop->setAccessible(true);
            $prop->setValue($ideia, $criado);
            $propUpd = $ref->getProperty('atualizadoEm');
            $propUpd->setAccessible(true);
            $propUpd->setValue($ideia, $criado);
        }

        $this->em->persist($ideia);

        return $ideia;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function persistDecisao(Empresa $empresa, User $autor, array $row): void
    {
        $decisao = new InovDecisao();
        $decisao->setEmpresa($empresa);
        $decisao->setAutor($autor);
        $decisao->setTitulo($row['title']);
        $decisao->setTipo($row['decision']);
        $decisao->setMotivo($row['reason']);
        $decisao->setOwnerNome($row['owner']);
        $decisao->setDecididoEm($this->parseBrDate($row['date']));

        $this->em->persist($decisao);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function persistConexao(Empresa $empresa, User $autor, array $row): void
    {
        $conexao = new InovConexao();
        $conexao->setEmpresa($empresa);
        $conexao->setAutor($autor);
        $conexao->setHub($row['hub']);
        $conexao->setIcon($row['icon']);
        $conexao->setSinergia($row['synergy']);
        $conexao->setStatus($row['status']);
        $conexao->setOportunidade($row['opportunity']);
        $conexao->setAcao($row['action']);

        $this->em->persist($conexao);
    }

    /**
     * @param array<string, InovIdeia> $ideiasByCodigo
     * @param array<string, mixed> $row
     */
    private function persistImpact(Empresa $empresa, array $ideiasByCodigo, array $row): void
    {
        $entry = new InovImpactEntry();
        $entry->setEmpresa($empresa);
        $entry->setTitulo($row['title']);
        $entry->setEstagioLabel($row['stage']);
        $entry->setValorCapturado($row['value'] !== '—' ? $row['value'] : null);
        $entry->setRoi($row['roi'] !== '—' ? $row['roi'] : null);
        $entry->setStatus($row['status']);

        if (isset($row['ideia_codigo'], $ideiasByCodigo[$row['ideia_codigo']])) {
            $entry->setIdeia($ideiasByCodigo[$row['ideia_codigo']]);
        }

        $this->em->persist($entry);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function persistTendencia(Empresa $empresa, array $row, int $ordem): void
    {
        $tendencia = new InovTendencia();
        $tendencia->setEmpresa($empresa);
        $tendencia->setLabel($row['label']);
        $tendencia->setValor($row['value']);
        $tendencia->setHint($row['hint']);
        $tendencia->setStatus($row['status']);
        $tendencia->setOrdem($ordem);

        $this->em->persist($tendencia);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function persistNovidade(Empresa $empresa, User $autor, array $row, int $daysAgo): void
    {
        $novidade = new InovNovidade();
        $novidade->setEmpresa($empresa);
        $novidade->setAutor($autor);
        $novidade->setTitulo($row['title']);
        $novidade->setResumo($row['summary']);
        $novidade->setIcon($row['icon']);
        $novidade->setRouteName($row['route'] ?? null);
        $novidade->setBadge($row['badge'] ?? null);
        $novidade->setVariant($row['variant']);
        $novidade->setPublicadoEm((new \DateTimeImmutable())->modify('-' . $daysAgo . ' days'));

        $this->em->persist($novidade);
    }

    private function parseBrDate(string $date): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('d/m/Y', $date . '/2026');

        return $parsed ?: new \DateTimeImmutable();
    }

    /** @return list<array<string, mixed>> */
    private function pipelineSeed(): array
    {
        return [
            ['codigo' => 'I01', 'title' => 'Assistente de triagem de currículos por IA', 'summary' => 'Usar LLM para pré-filtrar candidatos com base em critérios configuráveis.', 'owner' => 'Ana Costa', 'tags' => ['IA', 'RH'], 'progress' => 0, 'stage' => InovIdeia::STAGE_IDEIA, 'days' => 3, 'impact' => 80, 'effort' => 25, 'votes' => 12],
            ['codigo' => 'I02', 'title' => 'Dashboard unificado de ESG por empresa', 'summary' => 'KPIs ambientais e sociais consolidados por tenant.', 'owner' => 'Pedro Lima', 'tags' => ['ESG', 'Analytics'], 'progress' => 0, 'stage' => InovIdeia::STAGE_IDEIA, 'days' => 5, 'impact' => 85, 'effort' => 75, 'votes' => 9],
            ['codigo' => 'I03', 'title' => 'Gamificação de metas da equipe', 'summary' => 'Pontos, badges e leaderboard interno integrado ao Núcleo Talentos.', 'owner' => 'Juliana Torres', 'tags' => ['Talentos', 'Produto'], 'progress' => 0, 'stage' => InovIdeia::STAGE_IDEIA, 'days' => 1, 'impact' => 35, 'effort' => 20, 'votes' => 4],
            ['codigo' => 'I04', 'title' => 'Copiloto de contratos com IA', 'summary' => 'Revisão automática de cláusulas e alertas de risco em contratos corporativos.', 'owner' => 'Rafael Souza', 'tags' => ['IA', 'Legal'], 'progress' => 0, 'stage' => InovIdeia::STAGE_IDEIA, 'days' => 2, 'impact' => 78, 'effort' => 70, 'votes' => 7],
            ['codigo' => 'I05', 'title' => 'Programa de hackathons trimestrais', 'summary' => 'Eventos internos de co-criação com premiação e pipeline automático para POC.', 'owner' => 'Camila Dias', 'tags' => ['Cultura', 'Pessoas'], 'progress' => 0, 'stage' => InovIdeia::STAGE_IDEIA, 'days' => 4, 'impact' => 72, 'effort' => 30, 'votes' => 15],
            ['codigo' => 'I06', 'title' => 'Integração Slack × Cortex', 'summary' => 'Comandos slash para resumir projetos, OKRs e alertas direto no Slack.', 'owner' => 'Bruno Almeida', 'tags' => ['IA', 'Integrações'], 'progress' => 0, 'stage' => InovIdeia::STAGE_IDEIA, 'days' => 6, 'impact' => 45, 'effort' => 35, 'votes' => 6],
            ['codigo' => 'H01', 'title' => 'OCR automático de documentos no onboarding', 'summary' => 'Eliminar upload manual; extrair dados de documentos via visão computacional.', 'owner' => 'Carlos Neves', 'tags' => ['IA', 'RH'], 'progress' => 25, 'stage' => InovIdeia::STAGE_HIPOTESE, 'days' => 12, 'metric' => '< 2 min por doc.', 'rigor' => 60],
            ['codigo' => 'H02', 'title' => 'Notificações preditivas de férias', 'summary' => 'Alertar gestores 30 dias antes de saldo vencer; reduzir acúmulo.', 'owner' => 'Fernanda Gomes', 'tags' => ['RH', 'Notificações'], 'progress' => 40, 'stage' => InovIdeia::STAGE_HIPOTESE, 'days' => 8, 'metric' => '↓ 40 % saldo vencido', 'rigor' => 60],
            ['codigo' => 'P01', 'title' => 'Cortex integrado ao pipeline de projetos', 'summary' => 'Resumo automático de tarefas atrasadas e sugestão de reatribuição.', 'owner' => 'Marcos Ribeiro', 'tags' => ['IA', 'Projetos'], 'progress' => 65, 'stage' => InovIdeia::STAGE_POC, 'days' => 22, 'metric' => 'NPS gestor ≥ 7', 'rigor' => 85],
            ['codigo' => 'P02', 'title' => 'Mapa de calor de carga operacional', 'summary' => 'Visualizar sobrecarga por equipe em tempo real; priorizar contratações.', 'owner' => 'Luana Pereira', 'tags' => ['Analytics', 'Pessoas'], 'progress' => 55, 'stage' => InovIdeia::STAGE_POC, 'days' => 18, 'metric' => '↓ 15 % rotatividade', 'rigor' => 85],
            ['codigo' => 'T01', 'title' => 'Oferta de treinamento por análise de performance', 'summary' => 'Sugerir cursos com base em lacunas detectadas nas avaliações 180°.', 'owner' => 'Roberto Alves', 'tags' => ['Academy', 'IA'], 'progress' => 80, 'stage' => InovIdeia::STAGE_PILOTO, 'days' => 34, 'metric' => '+25 % conclusão curso'],
            ['codigo' => 'S01', 'title' => 'Helia como assistente padrão do hub RH', 'summary' => 'IA conversacional ativa em todos os fluxos do módulo RH.', 'owner' => 'Time Produto', 'tags' => ['IA', 'RH', 'Escala'], 'progress' => 100, 'stage' => InovIdeia::STAGE_ESCALA, 'days' => 62, 'metric' => '97 % satisfaction'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function decisionSeed(): array
    {
        return [
            ['title' => 'Chatbot de dúvidas de RH', 'decision' => InovDecisao::TIPO_KILL, 'date' => '15/05', 'reason' => 'Custo de manutenção > economia gerada; MVP rejeitado por usuários.', 'owner' => 'Time Produto'],
            ['title' => 'Dashboard mobile para gestores', 'decision' => InovDecisao::TIPO_PIVOT, 'date' => '08/05', 'reason' => 'Web responsivo suficiente; energia redirecionada para notificações push.', 'owner' => 'Luana Pereira'],
            ['title' => 'Onboarding digital automatizado', 'decision' => InovDecisao::TIPO_SCALE, 'date' => '01/05', 'reason' => 'NPS 8,7 no piloto; ROI 2,3× após 30 dias. Aprovado para todos os tenants.', 'owner' => 'Ana Costa'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function conexaoSeed(): array
    {
        return [
            ['hub' => 'Cortex', 'icon' => 'fa-brain', 'synergy' => 92, 'status' => InovConexao::STATUS_ACTIVE, 'opportunity' => 'Resumo automático de tarefas atrasadas + sugestão de reatribuição via Cortex.', 'action' => 'Integrar pipeline ao Cortex API'],
            ['hub' => 'RH', 'icon' => 'fa-users', 'synergy' => 87, 'status' => InovConexao::STATUS_ACTIVE, 'opportunity' => 'OCR de documentos + notificações preditivas de férias reduzem carga operacional.', 'action' => 'Ativar POC de OCR para onboarding'],
            ['hub' => 'Talentos', 'icon' => 'fa-star', 'synergy' => 74, 'status' => InovConexao::STATUS_EXPLORE, 'opportunity' => 'Gamificação de metas conectada ao sistema de performance e trilhas de desenvolvimento.', 'action' => 'Prototipar integração de badges'],
            ['hub' => 'Academy', 'icon' => 'fa-graduation-cap', 'synergy' => 81, 'status' => InovConexao::STATUS_ACTIVE, 'opportunity' => 'Sugerir cursos baseado em lacunas detectadas nas avaliações 180°.', 'action' => 'MVP: recomendar cursos por IA'],
            ['hub' => 'ESG', 'icon' => 'fa-leaf', 'synergy' => 65, 'status' => InovConexao::STATUS_EXPLORE, 'opportunity' => 'KPIs ambientais como dimensão adicional no radar de maturidade da inovação.', 'action' => 'Adicionar dimensão ESG ao radar'],
            ['hub' => 'Analytics', 'icon' => 'fa-chart-line', 'synergy' => 78, 'status' => InovConexao::STATUS_EXPLORE, 'opportunity' => 'Mapa de calor de carga operacional para prever contratações com antecedência.', 'action' => 'Dashboard de previsão de headcount'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function impactSeed(): array
    {
        return [
            ['title' => 'Helia no Núcleo RH', 'stage' => 'Escala', 'value' => 'R$ 87 k', 'roi' => '3,4×', 'status' => 'active', 'ideia_codigo' => 'S01'],
            ['title' => 'Treinamento por performance', 'stage' => 'Piloto', 'value' => 'R$ 42 k', 'roi' => '2,1×', 'status' => 'piloting', 'ideia_codigo' => 'T01'],
            ['title' => 'Cortex × Projetos', 'stage' => 'POC', 'value' => 'R$ 13 k', 'roi' => '—', 'status' => 'testing', 'ideia_codigo' => 'P01'],
            ['title' => 'OCR onboarding', 'stage' => 'Hipótese', 'value' => '—', 'roi' => '—', 'status' => 'hypothesis', 'ideia_codigo' => 'H01'],
            ['title' => 'Notificações preditivas de férias', 'stage' => 'Hipótese', 'value' => '—', 'roi' => '—', 'status' => 'hypothesis', 'ideia_codigo' => 'H02'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function tendenciaSeed(): array
    {
        return [
            ['label' => 'IA Generativa', 'value' => 92, 'hint' => 'LLMs, copilotos e automação cognitiva', 'status' => 'hot'],
            ['label' => 'ESG & Compliance', 'value' => 78, 'hint' => 'Relatórios, auditoria e governança', 'status' => 'rising'],
            ['label' => 'People Analytics', 'value' => 71, 'hint' => 'Dados de RH, turnover e performance', 'status' => 'rising'],
            ['label' => 'Low-Code / RPA', 'value' => 65, 'hint' => 'Automação de processos repetitivos', 'status' => 'stable'],
            ['label' => 'IoT Industrial', 'value' => 48, 'hint' => 'Sensores e monitoramento de campo', 'status' => 'watch'],
            ['label' => 'Blockchain', 'value' => 22, 'hint' => 'Rastreabilidade e contratos inteligentes', 'status' => 'cooling'],
            ['label' => 'Realidade Aumentada', 'value' => 35, 'hint' => 'Treinamento imersivo e manutenção', 'status' => 'watch'],
            ['label' => 'Quantum Computing', 'value' => 12, 'hint' => 'Pesquisa e experimentação de longo prazo', 'status' => 'research'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function novidadeSeed(): array
    {
        return [
            ['title' => 'Núcleo Inovação expandido', 'summary' => 'Novos módulos de Tendências, Portfólio e feed de Novidades disponíveis.', 'icon' => 'fa-lightbulb', 'route' => 'app_inovacao', 'badge' => 'Novo', 'variant' => 'success'],
            ['title' => 'Framework Kill · Pivot · Scale', 'summary' => 'Metodologia unificada para encerrar ciclos de experimentação.', 'icon' => 'fa-vial', 'route' => 'app_inovacao_experimentos', 'badge' => 'Metodologia', 'variant' => 'info'],
            ['title' => '6 ideias no backlog', 'summary' => 'Sessão de priorização recomendada para Quick Wins identificados.', 'icon' => 'fa-inbox', 'route' => 'app_inovacao_backlog', 'badge' => 'Alerta', 'variant' => 'warning'],
            ['title' => 'Radar de maturidade atualizado', 'summary' => '6 dimensões com dados em tempo real dos hubs conectados.', 'icon' => 'fa-chart-pie', 'route' => 'app_inovacao_analytics', 'badge' => 'Update', 'variant' => 'secondary'],
            ['title' => 'Helia escalada globalmente', 'summary' => 'Assistente IA do RH disponível em todos os tenants da plataforma.', 'icon' => 'fa-rocket', 'route' => 'app_inovacao_portfolio', 'badge' => 'Scale', 'variant' => 'success'],
            ['title' => '4 sinergias cross-núcleo detectadas', 'summary' => 'Conexões entre RH, Projetos, Cortex e Talentos prontas para POC.', 'icon' => 'fa-share-nodes', 'route' => 'app_inovacao_conexoes', 'badge' => 'Insight', 'variant' => 'info'],
        ];
    }
}
