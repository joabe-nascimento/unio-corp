<?php

namespace App\Entity;

use App\Repository\JuridicoJurisprudenciaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoJurisprudenciaRepository::class)]
#[ORM\Table(name: 'juridico_jurisprudencia')]
#[ORM\Index(columns: ['empresa_id', 'relevancia'], name: 'IDX_JUR_JURISPR_EMPRESA_RELEV')]
class JuridicoJurisprudencia
{
    public const RELEVANCIA_ALTA = 'alta';
    public const RELEVANCIA_MEDIA = 'media';
    public const RELEVANCIA_BAIXA = 'baixa';

    public const RELEVANCIAS = [self::RELEVANCIA_ALTA, self::RELEVANCIA_MEDIA, self::RELEVANCIA_BAIXA];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 40)]
    private string $tribunal;

    #[ORM\Column(length: 220)]
    private string $tema;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $data = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $resultado = null;

    #[ORM\Column(length: 10)]
    private string $relevancia = self::RELEVANCIA_MEDIA;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $referencia = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resumo = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $atualizadoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getTribunal(): string { return $this->tribunal; }
    public function setTribunal(string $tribunal): static { $this->tribunal = $tribunal; return $this; }
    public function getTema(): string { return $this->tema; }
    public function setTema(string $tema): static { $this->tema = $tema; return $this; }
    public function getData(): ?\DateTimeImmutable { return $this->data; }
    public function setData(?\DateTimeImmutable $data): static { $this->data = $data; return $this; }
    public function getResultado(): ?string { return $this->resultado; }
    public function setResultado(?string $resultado): static { $this->resultado = $resultado; return $this; }
    public function getRelevancia(): string { return $this->relevancia; }
    public function setRelevancia(string $relevancia): static { $this->relevancia = $relevancia; return $this; }
    public function getReferencia(): ?string { return $this->referencia; }
    public function setReferencia(?string $referencia): static { $this->referencia = $referencia; return $this; }
    public function getResumo(): ?string { return $this->resumo; }
    public function setResumo(?string $resumo): static { $this->resumo = $resumo; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): static { $this->createdBy = $createdBy; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }
}
