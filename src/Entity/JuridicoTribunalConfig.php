<?php

namespace App\Entity;

use App\Repository\JuridicoTribunalConfigRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Credencial de acesso à API Pública do DataJud (CNJ) por escritório — usada para
 * sincronizar andamentos oficiais de processos junto a PJe, e-SAJ, Projudi e demais
 * sistemas dos tribunais, todos indexados pela base nacional do CNJ.
 */
#[ORM\Entity(repositoryClass: JuridicoTribunalConfigRepository::class)]
#[ORM\Table(name: 'juridico_tribunal_config')]
class JuridicoTribunalConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $datajudApiKey = null;

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimoTesteEm = null;

    #[ORM\Column]
    private bool $ultimoTesteOk = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ultimoTesteMensagem = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalConsultas = 0;

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
    public function getDatajudApiKey(): ?string { return $this->datajudApiKey; }
    public function setDatajudApiKey(?string $datajudApiKey): static { $this->datajudApiKey = $datajudApiKey; return $this; }
    public function isConfigurado(): bool { return $this->datajudApiKey !== null && $this->datajudApiKey !== ''; }
    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }
    public function getUltimoTesteEm(): ?\DateTimeImmutable { return $this->ultimoTesteEm; }
    public function isUltimoTesteOk(): bool { return $this->ultimoTesteOk; }
    public function getUltimoTesteMensagem(): ?string { return $this->ultimoTesteMensagem; }

    public function registrarTeste(bool $ok, ?string $mensagem): static
    {
        $this->ultimoTesteEm = new \DateTimeImmutable();
        $this->ultimoTesteOk = $ok;
        $this->ultimoTesteMensagem = $mensagem;

        return $this;
    }

    public function registrarConsulta(): static
    {
        ++$this->totalConsultas;

        return $this;
    }

    public function getTotalConsultas(): int { return $this->totalConsultas; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }
}
