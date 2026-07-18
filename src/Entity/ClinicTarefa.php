<?php

namespace App\Entity;

use App\Repository\ClinicTarefaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicTarefaRepository::class)]
#[ORM\Table(name: 'clinic_tarefa')]
class ClinicTarefa
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_CONCLUIDA = 'concluida';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsavel = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $criadoPor = null;

    #[ORM\Column(length: 180)]
    private string $titulo = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $vencimento = null;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_PENDENTE;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $concluidaEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
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

    public function getResponsavel(): ?User
    {
        return $this->responsavel;
    }

    public function setResponsavel(?User $responsavel): static
    {
        $this->responsavel = $responsavel;

        return $this;
    }

    public function getCriadoPor(): ?User
    {
        return $this->criadoPor;
    }

    public function setCriadoPor(?User $criadoPor): static
    {
        $this->criadoPor = $criadoPor;

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

    public function getDescricao(): ?string
    {
        return $this->descricao;
    }

    public function setDescricao(?string $descricao): static
    {
        $this->descricao = $descricao;

        return $this;
    }

    public function getVencimento(): ?\DateTimeImmutable
    {
        return $this->vencimento;
    }

    public function setVencimento(?\DateTimeImmutable $vencimento): static
    {
        $this->vencimento = $vencimento;

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

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getConcluidaEm(): ?\DateTimeImmutable
    {
        return $this->concluidaEm;
    }

    public function setConcluidaEm(?\DateTimeImmutable $concluidaEm): static
    {
        $this->concluidaEm = $concluidaEm;

        return $this;
    }
}
