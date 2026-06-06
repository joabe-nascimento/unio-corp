<?php

namespace App\Entity;

use App\Repository\RhCandidatoRepository;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhCandidatoOrigem;
use App\Rh\RhEntrevistaTipo;
use App\Rh\RhRecrutamentoDisplay;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhCandidatoRepository::class)]
#[ORM\Table(name: 'rh_candidato')]
class RhCandidato
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RhVaga::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RhVaga $vaga;

    #[ORM\Column(length: 150)]
    private string $nome;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $telefone = null;

    #[ORM\Column(length: 32)]
    private string $etapa;

    #[ORM\Column(length: 32)]
    private string $origem = RhCandidatoOrigem::MANUAL;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacoes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motivoReprovacao = null;

    #[ORM\Column(nullable: true)]
    private ?int $avaliacao = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $linkedin = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $entrevistaEm = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $entrevistaLink = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $entrevistaTipo = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'entrevista_entrevistador_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $entrevistaEntrevistador = null;

    /** @var array<string, array<string, mixed>>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $scorecards = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $recrutador = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $noBancoTalentos = false;

    #[ORM\ManyToOne(targetEntity: RhOnboardingProcess::class)]
    #[ORM\JoinColumn(name: 'onboarding_process_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?RhOnboardingProcess $onboardingProcess = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getVaga(): RhVaga { return $this->vaga; }
    public function setVaga(RhVaga $vaga): static { $this->vaga = $vaga; return $this; }

    public function getNome(): string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getTelefone(): ?string { return $this->telefone; }
    public function setTelefone(?string $telefone): static { $this->telefone = $telefone; return $this; }

    public function getEtapa(): string { return $this->etapa; }
    public function setEtapa(string $etapa): static { $this->etapa = $etapa; return $this; }

    public function getEtapaLabel(): string
    {
        return RhCandidatoEtapa::label($this->etapa);
    }

    public function getEtapaClass(): string
    {
        return RhCandidatoEtapa::badgeVariant($this->etapa);
    }

    public function getOrigem(): string { return $this->origem; }
    public function setOrigem(string $origem): static { $this->origem = $origem; return $this; }

    public function getOrigemLabel(): string
    {
        return RhCandidatoOrigem::label($this->origem);
    }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $observacoes): static { $this->observacoes = $observacoes; return $this; }

    public function getMotivoReprovacao(): ?string { return $this->motivoReprovacao; }
    public function setMotivoReprovacao(?string $motivoReprovacao): static { $this->motivoReprovacao = $motivoReprovacao; return $this; }

    public function getAvaliacao(): ?int { return $this->avaliacao; }
    public function setAvaliacao(?int $avaliacao): static { $this->avaliacao = $avaliacao; return $this; }

    public function getLinkedin(): ?string { return $this->linkedin; }
    public function setLinkedin(?string $linkedin): static { $this->linkedin = $linkedin; return $this; }

    public function getEntrevistaEm(): ?\DateTimeImmutable { return $this->entrevistaEm; }
    public function setEntrevistaEm(?\DateTimeImmutable $entrevistaEm): static { $this->entrevistaEm = $entrevistaEm; return $this; }
    public function getEntrevistaLink(): ?string { return $this->entrevistaLink; }
    public function setEntrevistaLink(?string $entrevistaLink): static { $this->entrevistaLink = $entrevistaLink; return $this; }
    public function getEntrevistaTipo(): ?string { return $this->entrevistaTipo; }
    public function setEntrevistaTipo(?string $entrevistaTipo): static { $this->entrevistaTipo = $entrevistaTipo; return $this; }
    public function getEntrevistaTipoLabel(): string
    {
        return RhEntrevistaTipo::label($this->entrevistaTipo);
    }
    public function getEntrevistaEntrevistador(): ?User { return $this->entrevistaEntrevistador; }
    public function setEntrevistaEntrevistador(?User $entrevistaEntrevistador): static
    {
        $this->entrevistaEntrevistador = $entrevistaEntrevistador;

        return $this;
    }
    public function getEntrevistaTitulo(): ?string
    {
        if ($this->entrevistaEm === null) {
            return null;
        }

        return RhRecrutamentoDisplay::entrevistaTitulo($this);
    }
    /** @return array<string, array<string, mixed>>|null */
    public function getScorecards(): ?array { return $this->scorecards; }
    /** @param array<string, array<string, mixed>>|null $scorecards */
    public function setScorecards(?array $scorecards): static { $this->scorecards = $scorecards; return $this; }
    public function getRecrutador(): ?User { return $this->recrutador; }
    public function setRecrutador(?User $recrutador): static { $this->recrutador = $recrutador; return $this; }
    public function isNoBancoTalentos(): bool { return $this->noBancoTalentos; }
    public function setNoBancoTalentos(bool $noBancoTalentos): static { $this->noBancoTalentos = $noBancoTalentos; return $this; }

    public function getOnboardingProcess(): ?RhOnboardingProcess { return $this->onboardingProcess; }
    public function setOnboardingProcess(?RhOnboardingProcess $onboardingProcess): static { $this->onboardingProcess = $onboardingProcess; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
