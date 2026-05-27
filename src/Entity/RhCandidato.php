<?php

namespace App\Entity;

use App\Repository\RhCandidatoRepository;
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

    public function getOnboardingProcess(): ?RhOnboardingProcess { return $this->onboardingProcess; }
    public function setOnboardingProcess(?RhOnboardingProcess $onboardingProcess): static { $this->onboardingProcess = $onboardingProcess; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
