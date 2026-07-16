<?php

namespace App\Entity;

use App\Repository\ClinicProcedimentoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicProcedimentoRepository::class)]
#[ORM\Table(name: 'clinic_procedimento')]
#[ORM\UniqueConstraint(name: 'UNIQ_CLINIC_PROCEDIMENTO_CODIGO_INTERNO', columns: ['empresa_id', 'codigo_interno'])]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_CLINIC_PROCEDIMENTO_EMPRESA')]
class ClinicProcedimento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 180)]
    private string $nome = '';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $codigoInterno = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codigoTuss = null;

    #[ORM\Column(nullable: true)]
    private ?int $valorCentavos = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 30])]
    private int $duracaoMinutos = 30;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $ativo = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->criadoEm = $now;
        $this->atualizadoEm = $now;
    }

    public function touch(): void
    {
        $this->atualizadoEm = new \DateTimeImmutable();
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

    public function getCodigoInterno(): ?string
    {
        return $this->codigoInterno;
    }

    public function setCodigoInterno(?string $codigoInterno): static
    {
        $this->codigoInterno = $codigoInterno;

        return $this;
    }

    public function getCodigoTuss(): ?string
    {
        return $this->codigoTuss;
    }

    public function setCodigoTuss(?string $codigoTuss): static
    {
        $this->codigoTuss = $codigoTuss;

        return $this;
    }

    public function getValorCentavos(): ?int
    {
        return $this->valorCentavos;
    }

    public function setValorCentavos(?int $valorCentavos): static
    {
        $this->valorCentavos = $valorCentavos;

        return $this;
    }

    public function getDuracaoMinutos(): int
    {
        return $this->duracaoMinutos;
    }

    public function setDuracaoMinutos(int $duracaoMinutos): static
    {
        $this->duracaoMinutos = $duracaoMinutos;

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

    public function isAtivo(): bool
    {
        return $this->ativo;
    }

    public function setAtivo(bool $ativo): static
    {
        $this->ativo = $ativo;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getAtualizadoEm(): \DateTimeImmutable
    {
        return $this->atualizadoEm;
    }
}
