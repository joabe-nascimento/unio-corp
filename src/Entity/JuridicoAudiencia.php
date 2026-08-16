<?php

namespace App\Entity;

use App\Repository\JuridicoAudienciaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoAudienciaRepository::class)]
#[ORM\Table(name: 'juridico_audiencia')]
#[ORM\Index(columns: ['empresa_id', 'data_hora'], name: 'IDX_JUR_AUD_DATA')]
class JuridicoAudiencia
{
    public const STATUS_AGENDADA = 'agendada';
    public const STATUS_REALIZADA = 'realizada';
    public const STATUS_CANCELADA = 'cancelada';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoProcesso $processo = null;

    #[ORM\Column(length: 80)]
    private string $tipo = 'instrução';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $local = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $linkVirtual = null;

    #[ORM\Column]
    private \DateTimeImmutable $dataHora;

    /** @var list<array{item: string, feito: bool}>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $checklist = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $roteiro = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ata = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_AGENDADA;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsavel = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $atualizadoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->dataHora = new \DateTimeImmutable('+7 days 09:00');
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getProcesso(): ?JuridicoProcesso { return $this->processo; }
    public function setProcesso(?JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }
    public function getLocal(): ?string { return $this->local; }
    public function setLocal(?string $local): static { $this->local = $local; return $this; }
    public function getLinkVirtual(): ?string { return $this->linkVirtual; }
    public function setLinkVirtual(?string $linkVirtual): static { $this->linkVirtual = $linkVirtual; return $this; }
    public function getDataHora(): \DateTimeImmutable { return $this->dataHora; }
    public function setDataHora(\DateTimeImmutable $dataHora): static { $this->dataHora = $dataHora; return $this; }
    /** @return list<array{item: string, feito: bool}> */
    public function getChecklist(): array { return $this->checklist ?? []; }
    /** @param list<array{item: string, feito: bool}>|null $checklist */
    public function setChecklist(?array $checklist): static { $this->checklist = $checklist; return $this; }
    public function getRoteiro(): ?string { return $this->roteiro; }
    public function setRoteiro(?string $roteiro): static { $this->roteiro = $roteiro; return $this; }
    public function getAta(): ?string { return $this->ata; }
    public function setAta(?string $ata): static { $this->ata = $ata; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getResponsavel(): ?User { return $this->responsavel; }
    public function setResponsavel(?User $responsavel): static { $this->responsavel = $responsavel; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }
}
