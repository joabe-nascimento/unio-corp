<?php

namespace App\Entity;

use App\Repository\EmpresaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmpresaRepository::class)]
class Empresa
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nome = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $cnpj = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $setor = null;

    #[ORM\Column(nullable: true)]
    private ?string $logo = null;

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column(length: 80, nullable: true, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $carreirasAtivo = false;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $carreirasTitulo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $carreirasDescricao = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $recruitmentIntegracoes = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'empresa')]
    private Collection $usuarios;

    #[ORM\OneToMany(targetEntity: Funcionario::class, mappedBy: 'empresa')]
    private Collection $funcionarios;

    #[ORM\OneToMany(targetEntity: Departamento::class, mappedBy: 'empresa')]
    private Collection $departamentos;

    public function __construct()
    {
        $this->criadoEm   = new \DateTimeImmutable();
        $this->usuarios    = new ArrayCollection();
        $this->funcionarios = new ArrayCollection();
        $this->departamentos = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNome(): ?string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }
    public function getCnpj(): ?string { return $this->cnpj; }
    public function setCnpj(string $cnpj): static { $this->cnpj = $cnpj; return $this; }
    public function getSetor(): ?string { return $this->setor; }
    public function setSetor(?string $setor): static { $this->setor = $setor; return $this; }
    public function getLogo(): ?string { return $this->logo; }
    public function setLogo(?string $logo): static { $this->logo = $logo; return $this; }
    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): static { $this->slug = $slug; return $this; }
    public function isCarreirasAtivo(): bool { return $this->carreirasAtivo; }
    public function setCarreirasAtivo(bool $carreirasAtivo): static { $this->carreirasAtivo = $carreirasAtivo; return $this; }
    public function getCarreirasTitulo(): ?string { return $this->carreirasTitulo; }
    public function setCarreirasTitulo(?string $carreirasTitulo): static { $this->carreirasTitulo = $carreirasTitulo; return $this; }
    public function getCarreirasDescricao(): ?string { return $this->carreirasDescricao; }
    public function setCarreirasDescricao(?string $carreirasDescricao): static { $this->carreirasDescricao = $carreirasDescricao; return $this; }
    /** @return array<string, mixed>|null */
    public function getRecruitmentIntegracoes(): ?array { return $this->recruitmentIntegracoes; }
    /** @param array<string, mixed>|null $recruitmentIntegracoes */
    public function setRecruitmentIntegracoes(?array $recruitmentIntegracoes): static { $this->recruitmentIntegracoes = $recruitmentIntegracoes; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getUsuarios(): Collection { return $this->usuarios; }
    public function getFuncionarios(): Collection { return $this->funcionarios; }
    public function getDepartamentos(): Collection { return $this->departamentos; }
}
