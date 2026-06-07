<?php

namespace App\Entity;

use App\Repository\TiChamadoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiChamadoRepository::class)]
#[ORM\Table(name: 'ti_chamado')]
#[ORM\Index(name: 'IDX_TI_CHAMADO_EMPRESA_STATUS', columns: ['empresa_id', 'status'])]
#[ORM\Index(name: 'IDX_TI_CHAMADO_EMPRESA_ABERTO', columns: ['empresa_id', 'aberto_em'])]
#[ORM\UniqueConstraint(name: 'UNIQ_TI_CHAMADO_CODIGO', columns: ['empresa_id', 'codigo'])]
class TiChamado
{
    public const STATUS_NOVO = 'novo';
    public const STATUS_EM_ANALISE = 'em_analise';
    public const STATUS_EM_EXECUCAO = 'em_execucao';
    public const STATUS_AGUARDANDO = 'aguardando';
    public const STATUS_RESOLVIDO = 'resolvido';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $solicitante;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsavel = null;

    #[ORM\Column(length: 16)]
    private string $codigo = '';

    #[ORM\Column(length: 200)]
    private string $titulo;

    #[ORM\Column(type: 'text')]
    private string $resumo;

    #[ORM\Column(length: 32)]
    private string $categoria = 'sistema';

    #[ORM\Column(length: 4)]
    private string $prioridade = 'P3';

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_NOVO;

    #[ORM\Column(length: 16)]
    private string $impacto = 'medio';

    #[ORM\Column(length: 32)]
    private string $local = 'matriz';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $assetTag = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $catalogItem = null;

    #[ORM\Column(type: 'smallint')]
    private int $usuariosAfetados = 1;

    #[ORM\Column(length: 24)]
    private string $canalContato = 'portal';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefoneContato = null;

    #[ORM\Column(type: 'boolean')]
    private bool $notificarGestor = false;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $horarioPreferido = null;

    #[ORM\Column(type: 'smallint')]
    private int $slaPct = 100;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $heliaConfianca = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $heliaAnalise = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $heliaKb = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $tags = [];

    /** @var list<array{at: string, event: string, actor: string}> */
    #[ORM\Column(type: 'json')]
    private array $timeline = [];

    #[ORM\Column(type: 'boolean')]
    private bool $heliaAplicado = false;

    #[ORM\Column(type: 'boolean')]
    private bool $heliaRevisado = false;

    #[ORM\ManyToOne(targetEntity: TiProblema::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TiProblema $problema = null;

    #[ORM\ManyToOne(targetEntity: TiAtivo::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TiAtivo $ativo = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $slaPausadoEm = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $slaPausadoMotivo = null;

    #[ORM\Column(type: 'integer')]
    private int $slaPausadoAcumuladoSeg = 0;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $heliaFeedback = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $heliaFeedbackEm = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $integConectorId = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $csatScore = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $csatComentario = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $csatEm = null;

    /** @var list<array{step: int, titulo: string, feito: bool, evidencia: string|null, feito_em: string|null}> */
    #[ORM\Column(type: 'json')]
    private array $playbookSteps = [];

    /** @var Collection<int, TiChamadoAnexo> */
    #[ORM\OneToMany(mappedBy: 'chamado', targetEntity: TiChamadoAnexo::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['criadoEm' => 'ASC'])]
    private Collection $anexos;

    #[ORM\Column]
    private \DateTimeImmutable $abertoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvidoEm = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->abertoEm = $now;
        $this->criadoEm = $now;
        $this->atualizadoEm = $now;
        $this->anexos = new ArrayCollection();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $requester = $this->solicitante->getNome() ?: $this->solicitante->getEmail() ?: 'Usuário';
        $assignee = $this->responsavel
            ? ($this->responsavel->getNome() ?: $this->responsavel->getEmail() ?: '—')
            : '—';

        return [
            'id' => $this->codigo,
            'title' => $this->titulo,
            'summary' => $this->resumo,
            'priority' => $this->prioridade,
            'status' => $this->status,
            'assignee' => $assignee,
            'requester' => $requester,
            'requester_id' => $this->solicitante->getId(),
            'solicitante_id' => $this->solicitante->getId(),
            'tags' => $this->tags,
            'sla_pct' => $this->slaPct,
            'opened_at' => $this->abertoEm->format('Y-m-d H:i'),
            'resolved_at' => $this->resolvidoEm?->format('Y-m-d H:i'),
            'category' => $this->categoria,
            'channel' => $this->canalContato,
            'contact_channel' => $this->canalContato,
            'contact_phone' => $this->telefoneContato,
            'notify_manager' => $this->notificarGestor,
            'preferred_time' => $this->horarioPreferido,
            'impact' => $this->impacto,
            'location' => $this->local,
            'asset_tag' => $this->assetTag,
            'catalog_item' => $this->catalogItem,
            'affected_users' => $this->usuariosAfetados,
            'helia_confidence' => $this->heliaConfianca,
            'helia_analysis' => $this->heliaAnalise,
            'helia_kb' => $this->heliaKb,
            'helia_applied' => $this->heliaAplicado,
            'helia_reviewed' => $this->heliaRevisado,
            'helia_feedback' => $this->heliaFeedback,
            'problema_id' => $this->problema?->getCodigo(),
            'problema_db_id' => $this->problema?->getId(),
            'ativo_id' => $this->ativo?->getId(),
            'ativo_codigo' => $this->ativo?->getCodigo(),
            'sla_paused' => $this->slaPausadoEm !== null,
            'sla_pause_reason' => $this->slaPausadoMotivo,
            'assignee_id' => $this->responsavel?->getId(),
            'timeline' => $this->timeline,
            'integ_conector_id' => $this->integConectorId,
            'csat_score' => $this->csatScore,
            'csat_comentario' => $this->csatComentario,
            'csat_em' => $this->csatEm?->format('d/m/Y'),
            'playbook_steps' => $this->playbookSteps,
            'attachments' => array_map(
                static fn (TiChamadoAnexo $a) => $a->toArray(),
                $this->anexos->toArray(),
            ),
        ];
    }

    /** @return Collection<int, TiChamadoAnexo> */
    public function getAnexos(): Collection
    {
        return $this->anexos;
    }

    public function addAnexo(TiChamadoAnexo $anexo): static
    {
        if (!$this->anexos->contains($anexo)) {
            $this->anexos->add($anexo);
            $anexo->setChamado($this);
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getSolicitante(): User
    {
        return $this->solicitante;
    }

    public function setSolicitante(User $solicitante): static
    {
        $this->solicitante = $solicitante;

        return $this;
    }

    public function getResponsavel(): ?User
    {
        return $this->responsavel;
    }

    public function setResponsavel(?User $responsavel): static
    {
        $this->responsavel = $responsavel;

        return $this;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = $codigo;

        return $this;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): static
    {
        $this->titulo = $titulo;

        return $this;
    }

    public function getResumo(): string
    {
        return $this->resumo;
    }

    public function setResumo(string $resumo): static
    {
        $this->resumo = $resumo;

        return $this;
    }

    public function getCategoria(): string
    {
        return $this->categoria;
    }

    public function setCategoria(string $categoria): static
    {
        $this->categoria = $categoria;

        return $this;
    }

    public function getPrioridade(): string
    {
        return $this->prioridade;
    }

    public function setPrioridade(string $prioridade): static
    {
        $this->prioridade = $prioridade;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getImpacto(): string
    {
        return $this->impacto;
    }

    public function setImpacto(string $impacto): static
    {
        $this->impacto = $impacto;

        return $this;
    }

    public function getLocal(): string
    {
        return $this->local;
    }

    public function setLocal(string $local): static
    {
        $this->local = $local;

        return $this;
    }

    public function getAssetTag(): ?string
    {
        return $this->assetTag;
    }

    public function setAssetTag(?string $assetTag): static
    {
        $this->assetTag = $assetTag;

        return $this;
    }

    public function getCatalogItem(): ?string
    {
        return $this->catalogItem;
    }

    public function setCatalogItem(?string $catalogItem): static
    {
        $this->catalogItem = $catalogItem;

        return $this;
    }

    public function getUsuariosAfetados(): int
    {
        return $this->usuariosAfetados;
    }

    public function setUsuariosAfetados(int $usuariosAfetados): static
    {
        $this->usuariosAfetados = $usuariosAfetados;

        return $this;
    }

    public function getCanalContato(): string
    {
        return $this->canalContato;
    }

    public function setCanalContato(string $canalContato): static
    {
        $this->canalContato = $canalContato;

        return $this;
    }

    public function getTelefoneContato(): ?string
    {
        return $this->telefoneContato;
    }

    public function setTelefoneContato(?string $telefoneContato): static
    {
        $this->telefoneContato = $telefoneContato;

        return $this;
    }

    public function isNotificarGestor(): bool
    {
        return $this->notificarGestor;
    }

    public function setNotificarGestor(bool $notificarGestor): static
    {
        $this->notificarGestor = $notificarGestor;

        return $this;
    }

    public function getHorarioPreferido(): ?string
    {
        return $this->horarioPreferido;
    }

    public function setHorarioPreferido(?string $horarioPreferido): static
    {
        $this->horarioPreferido = $horarioPreferido;

        return $this;
    }

    public function getSlaPct(): int
    {
        return $this->slaPct;
    }

    public function setSlaPct(int $slaPct): static
    {
        $this->slaPct = max(0, min(100, $slaPct));

        return $this;
    }

    public function getHeliaConfianca(): ?int
    {
        return $this->heliaConfianca;
    }

    public function setHeliaConfianca(?int $heliaConfianca): static
    {
        $this->heliaConfianca = $heliaConfianca;

        return $this;
    }

    public function getHeliaAnalise(): ?string
    {
        return $this->heliaAnalise;
    }

    public function setHeliaAnalise(?string $heliaAnalise): static
    {
        $this->heliaAnalise = $heliaAnalise;

        return $this;
    }

    /** @return list<string> */
    public function getHeliaKb(): array
    {
        return $this->heliaKb;
    }

    /** @param list<string> $heliaKb */
    public function setHeliaKb(array $heliaKb): static
    {
        $this->heliaKb = $heliaKb;

        return $this;
    }

    /** @return list<string> */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @param list<string> $tags */
    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    /** @return list<array{at: string, event: string, actor: string}> */
    public function getTimeline(): array
    {
        return $this->timeline;
    }

    /** @param list<array{at: string, event: string, actor: string}> $timeline */
    public function setTimeline(array $timeline): static
    {
        $this->timeline = $timeline;

        return $this;
    }

    public function addTimelineEvent(string $event, string $actor): static
    {
        $timeline = $this->timeline;
        $timeline[] = [
            'at' => (new \DateTimeImmutable())->format('d/m H:i'),
            'event' => $event,
            'actor' => $actor,
        ];
        // Reatribuição necessária: Doctrine não detecta mutação in-place em colunas JSON.
        $this->timeline = $timeline;
        $this->atualizadoEm = new \DateTimeImmutable();

        return $this;
    }

    public function isHeliaAplicado(): bool
    {
        return $this->heliaAplicado;
    }

    public function setHeliaAplicado(bool $heliaAplicado): static
    {
        $this->heliaAplicado = $heliaAplicado;

        return $this;
    }

    public function isHeliaRevisado(): bool
    {
        return $this->heliaRevisado;
    }

    public function setHeliaRevisado(bool $heliaRevisado): static
    {
        $this->heliaRevisado = $heliaRevisado;

        return $this;
    }

    public function getProblema(): ?TiProblema
    {
        return $this->problema;
    }

    public function setProblema(?TiProblema $problema): static
    {
        $this->problema = $problema;

        return $this;
    }

    public function getAtivo(): ?TiAtivo
    {
        return $this->ativo;
    }

    public function setAtivo(?TiAtivo $ativo): static
    {
        $this->ativo = $ativo;

        return $this;
    }

    public function getSlaPausadoEm(): ?\DateTimeImmutable
    {
        return $this->slaPausadoEm;
    }

    public function setSlaPausadoEm(?\DateTimeImmutable $slaPausadoEm): static
    {
        $this->slaPausadoEm = $slaPausadoEm;

        return $this;
    }

    public function getSlaPausadoMotivo(): ?string
    {
        return $this->slaPausadoMotivo;
    }

    public function setSlaPausadoMotivo(?string $slaPausadoMotivo): static
    {
        $this->slaPausadoMotivo = $slaPausadoMotivo;

        return $this;
    }

    public function getSlaPausadoAcumuladoSeg(): int
    {
        return $this->slaPausadoAcumuladoSeg;
    }

    public function setSlaPausadoAcumuladoSeg(int $slaPausadoAcumuladoSeg): static
    {
        $this->slaPausadoAcumuladoSeg = max(0, $slaPausadoAcumuladoSeg);

        return $this;
    }

    public function getHeliaFeedback(): ?string
    {
        return $this->heliaFeedback;
    }

    public function setHeliaFeedback(?string $heliaFeedback): static
    {
        $this->heliaFeedback = $heliaFeedback;

        return $this;
    }

    public function getHeliaFeedbackEm(): ?\DateTimeImmutable
    {
        return $this->heliaFeedbackEm;
    }

    public function setHeliaFeedbackEm(?\DateTimeImmutable $heliaFeedbackEm): static
    {
        $this->heliaFeedbackEm = $heliaFeedbackEm;

        return $this;
    }

    public function getIntegConectorId(): ?string
    {
        return $this->integConectorId;
    }

    public function setIntegConectorId(?string $integConectorId): static
    {
        $this->integConectorId = $integConectorId;

        return $this;
    }

    public function getCsatScore(): ?int
    {
        return $this->csatScore;
    }

    public function setCsatScore(?int $csatScore): static
    {
        $this->csatScore = $csatScore !== null ? max(1, min(5, $csatScore)) : null;

        return $this;
    }

    public function getCsatComentario(): ?string
    {
        return $this->csatComentario;
    }

    public function setCsatComentario(?string $csatComentario): static
    {
        $this->csatComentario = $csatComentario;

        return $this;
    }

    public function getCsatEm(): ?\DateTimeImmutable
    {
        return $this->csatEm;
    }

    public function setCsatEm(?\DateTimeImmutable $csatEm): static
    {
        $this->csatEm = $csatEm;

        return $this;
    }

    /** @return list<array{step: int, titulo: string, feito: bool, evidencia: string|null, feito_em: string|null}> */
    public function getPlaybookSteps(): array
    {
        return $this->playbookSteps;
    }

    /** @param list<array{step: int, titulo: string, feito: bool, evidencia: string|null, feito_em: string|null}> $playbookSteps */
    public function setPlaybookSteps(array $playbookSteps): static
    {
        $this->playbookSteps = $playbookSteps;

        return $this;
    }

    public function getAbertoEm(): \DateTimeImmutable
    {
        return $this->abertoEm;
    }

    public function setAbertoEm(\DateTimeImmutable $abertoEm): static
    {
        $this->abertoEm = $abertoEm;

        return $this;
    }

    public function getResolvidoEm(): ?\DateTimeImmutable
    {
        return $this->resolvidoEm;
    }

    public function setResolvidoEm(?\DateTimeImmutable $resolvidoEm): static
    {
        $this->resolvidoEm = $resolvidoEm;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getAtualizadoEm(): \DateTimeImmutable
    {
        return $this->atualizadoEm;
    }

    public function touch(): static
    {
        $this->atualizadoEm = new \DateTimeImmutable();

        return $this;
    }
}
