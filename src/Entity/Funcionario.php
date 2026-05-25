<?php

namespace App\Entity;

use App\Repository\FuncionarioRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FuncionarioRepository::class)]
class Funcionario
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nome = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefone = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cargo = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataAdmissao = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataDemissao = null;

    #[ORM\Column(length: 20)]
    private string $status = 'ATIVO'; // ATIVO, INATIVO, FERIAS, AFASTADO

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $salario = null;

    #[ORM\Column(nullable: true)]
    private ?string $foto = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $nivelMaturidade = null; // INICIANTE, JUNIOR, PLENO, SENIOR, ESPECIALISTA

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $competencias = [];

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\ManyToOne(targetEntity: Empresa::class, inversedBy: 'funcionarios')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne(targetEntity: Departamento::class, inversedBy: 'funcionarios')]
    private ?Departamento $departamento = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getNome(): ?string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getTelefone(): ?string { return $this->telefone; }
    public function setTelefone(?string $t): static { $this->telefone = $t; return $this; }
    public function getCargo(): ?string { return $this->cargo; }
    public function setCargo(?string $c): static { $this->cargo = $c; return $this; }
    public function getDataAdmissao(): ?\DateTimeImmutable { return $this->dataAdmissao; }
    public function setDataAdmissao(?\DateTimeImmutable $d): static { $this->dataAdmissao = $d; return $this; }
    public function getDataDemissao(): ?\DateTimeImmutable { return $this->dataDemissao; }
    public function setDataDemissao(?\DateTimeImmutable $d): static { $this->dataDemissao = $d; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
    public function getSalario(): ?string { return $this->salario; }
    public function setSalario(?string $s): static { $this->salario = $s; return $this; }
    public function getFoto(): ?string { return $this->foto; }
    public function setFoto(?string $f): static { $this->foto = $f; return $this; }
    public function getNivelMaturidade(): ?string { return $this->nivelMaturidade; }
    public function setNivelMaturidade(?string $n): static { $this->nivelMaturidade = $n; return $this; }
    public function getCompetencias(): ?array { return $this->competencias; }
    public function setCompetencias(?array $c): static { $this->competencias = $c; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getEmpresa(): ?Empresa { return $this->empresa; }
    public function setEmpresa(?Empresa $e): static { $this->empresa = $e; return $this; }
    public function getDepartamento(): ?Departamento { return $this->departamento; }
    public function setDepartamento(?Departamento $d): static { $this->departamento = $d; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'ATIVO'     => 'Ativo',
            'INATIVO'   => 'Inativo',
            'FERIAS'    => 'Férias',
            'AFASTADO'  => 'Afastado',
            default     => $this->status,
        };
    }

    public function getStatusClass(): string
    {
        return match($this->status) {
            'ATIVO'    => 'success',
            'INATIVO'  => 'danger',
            'FERIAS'   => 'warning',
            'AFASTADO' => 'secondary',
            default    => 'info',
        };
    }

    public function getInitials(): string
    {
        $parts = explode(' ', $this->nome ?? '');
        return strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1)) ?: 'FN';
    }
}
