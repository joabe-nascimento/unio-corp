<?php

namespace App\Entity;

use App\Repository\JuridicoConflitoCheckRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoConflitoCheckRepository::class)]
#[ORM\Table(name: 'juridico_conflito_check')]
class JuridicoConflitoCheck
{
    public const RESULTADO_LIVRE = 'livre';
    public const RESULTADO_ALERTA = 'alerta';
    public const RESULTADO_BLOQUEIO = 'bloqueio';

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

    #[ORM\ManyToOne(targetEntity: JuridicoCliente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoCliente $cliente = null;

    #[ORM\Column(length: 180)]
    private string $nomeConsultado;

    #[ORM\Column(length: 24)]
    private string $resultado = self::RESULTADO_LIVRE;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $detalhes = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getProcesso(): ?JuridicoProcesso { return $this->processo; }
    public function setProcesso(?JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getCliente(): ?JuridicoCliente { return $this->cliente; }
    public function setCliente(?JuridicoCliente $cliente): static { $this->cliente = $cliente; return $this; }
    public function getNomeConsultado(): string { return $this->nomeConsultado; }
    public function setNomeConsultado(string $nomeConsultado): static { $this->nomeConsultado = $nomeConsultado; return $this; }
    public function getResultado(): string { return $this->resultado; }
    public function setResultado(string $resultado): static { $this->resultado = $resultado; return $this; }
    /** @return array<string, mixed>|null */
    public function getDetalhes(): ?array { return $this->detalhes; }
    /** @param array<string, mixed>|null $detalhes */
    public function setDetalhes(?array $detalhes): static { $this->detalhes = $detalhes; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
