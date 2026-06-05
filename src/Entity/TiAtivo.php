<?php

namespace App\Entity;

use App\Repository\TiAtivoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiAtivoRepository::class)]
#[ORM\Table(name: 'ti_ativo')]
#[ORM\UniqueConstraint(name: 'UNIQ_TI_ATIVO_CODIGO', columns: ['empresa_id', 'codigo'])]
class TiAtivo
{
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_MANUTENCAO = 'manutencao';
    public const STATUS_ESTOQUE = 'estoque';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 32)]
    private string $codigo;

    #[ORM\Column(length: 48)]
    private string $tipo;

    #[ORM\Column(length: 120)]
    private string $modelo;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $responsavel = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ATIVO;

    #[ORM\Column(type: 'smallint')]
    private int $cicloPct = 0;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->criadoEm = $now;
        $this->atualizadoEm = $now;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'id' => $this->codigo,
            'codigo' => $this->codigo,
            'type' => $this->tipo,
            'model' => $this->modelo,
            'owner' => $this->responsavel ?? '—',
            'responsavel' => $this->responsavel ?? '',
            'status' => $this->status,
            'lifecycle' => $this->cicloPct,
            'ciclo_pct' => $this->cicloPct,
        ];
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

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = $codigo;

        return $this;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getModelo(): string
    {
        return $this->modelo;
    }

    public function setModelo(string $modelo): static
    {
        $this->modelo = $modelo;

        return $this;
    }

    public function getResponsavel(): ?string
    {
        return $this->responsavel;
    }

    public function setResponsavel(?string $responsavel): static
    {
        $this->responsavel = $responsavel;

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

    public function getCicloPct(): int
    {
        return $this->cicloPct;
    }

    public function setCicloPct(int $cicloPct): static
    {
        $this->cicloPct = max(0, min(100, $cicloPct));

        return $this;
    }

    public function touch(): void
    {
        $this->atualizadoEm = new \DateTimeImmutable();
    }
}
