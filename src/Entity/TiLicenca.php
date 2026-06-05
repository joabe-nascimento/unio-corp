<?php

namespace App\Entity;

use App\Repository\TiLicencaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiLicencaRepository::class)]
#[ORM\Table(name: 'ti_licenca')]
class TiLicenca
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 120)]
    private string $nome;

    #[ORM\Column(type: 'smallint')]
    private int $seats;

    #[ORM\Column(type: 'smallint')]
    private int $used;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $custoMensal = '0';

    #[ORM\Column]
    private \DateTimeImmutable $renovacaoEm;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function burnPct(): int
    {
        if ($this->seats <= 0) {
            return 0;
        }

        return (int) round($this->used / $this->seats * 100);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'name' => $this->nome,
            'nome' => $this->nome,
            'seats' => $this->seats,
            'used' => $this->used,
            'cost' => 'R$ ' . number_format((float) $this->custoMensal, 1, ',', '.') . ' k/mês',
            'custo_mensal' => (float) $this->custoMensal,
            'renewal' => $this->renovacaoEm->format('d/m/Y'),
            'renovacao_em' => $this->renovacaoEm->format('Y-m-d'),
            'burn' => $this->burnPct(),
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

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

        return $this;
    }

    public function getSeats(): int
    {
        return $this->seats;
    }

    public function setSeats(int $seats): static
    {
        $this->seats = $seats;

        return $this;
    }

    public function getUsed(): int
    {
        return $this->used;
    }

    public function setUsed(int $used): static
    {
        $this->used = $used;

        return $this;
    }

    public function getCustoMensal(): string
    {
        return $this->custoMensal;
    }

    public function setCustoMensal(string $custoMensal): static
    {
        $this->custoMensal = $custoMensal;

        return $this;
    }

    public function getRenovacaoEm(): \DateTimeImmutable
    {
        return $this->renovacaoEm;
    }

    public function setRenovacaoEm(\DateTimeImmutable $renovacaoEm): static
    {
        $this->renovacaoEm = $renovacaoEm;

        return $this;
    }
}
