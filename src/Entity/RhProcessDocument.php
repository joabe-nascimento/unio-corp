<?php

namespace App\Entity;

use App\Repository\RhProcessDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhProcessDocumentRepository::class)]
#[ORM\Table(name: 'rh_process_document')]
class RhProcessDocument
{
    public const CAT_ADMISSIONAL = 'ADMISSIONAL';
    public const CAT_RESCISORIA = 'RESCISORIA';
    public const CAT_GERAL = 'GERAL';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: RhOnboardingProcess::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?RhOnboardingProcess $onboarding = null;

    #[ORM\ManyToOne(targetEntity: RhOffboardingProcess::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?RhOffboardingProcess $offboarding = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    #[ORM\Column(length: 255)]
    private string $nomeOriginal;

    #[ORM\Column(length: 500)]
    private string $caminho;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private int $tamanho = 0;

    #[ORM\Column(length: 32)]
    private string $categoria = self::CAT_GERAL;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getOnboarding(): ?RhOnboardingProcess { return $this->onboarding; }
    public function setOnboarding(?RhOnboardingProcess $onboarding): static { $this->onboarding = $onboarding; return $this; }

    public function getOffboarding(): ?RhOffboardingProcess { return $this->offboarding; }
    public function setOffboarding(?RhOffboardingProcess $offboarding): static { $this->offboarding = $offboarding; return $this; }

    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $uploadedBy): static { $this->uploadedBy = $uploadedBy; return $this; }

    public function getNomeOriginal(): string { return $this->nomeOriginal; }
    public function setNomeOriginal(string $nomeOriginal): static { $this->nomeOriginal = $nomeOriginal; return $this; }

    public function getCaminho(): string { return $this->caminho; }
    public function setCaminho(string $caminho): static { $this->caminho = $caminho; return $this; }

    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): static { $this->mimeType = $mimeType; return $this; }

    public function getTamanho(): int { return $this->tamanho; }
    public function setTamanho(int $tamanho): static { $this->tamanho = $tamanho; return $this; }

    public function getCategoria(): string { return $this->categoria; }
    public function setCategoria(string $categoria): static { $this->categoria = $categoria; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function getTamanhoLabel(): string
    {
        if ($this->tamanho < 1024) {
            return $this->tamanho . ' B';
        }
        if ($this->tamanho < 1048576) {
            return round($this->tamanho / 1024, 1) . ' KB';
        }

        return round($this->tamanho / 1048576, 1) . ' MB';
    }
}
