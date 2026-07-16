<?php

namespace App\Entity;

use App\Repository\ClinicConvenioRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicConvenioRepository::class)]
#[ORM\Table(name: 'clinic_convenio')]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_CLINIC_CONVENIO_EMPRESA')]
class ClinicConvenio
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

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $registroAns = null;

    #[ORM\Column(length: 14, nullable: true)]
    private ?string $cnpj = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $codigoPrestador = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $versaoTiss = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $contatoFaturamento = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $emailFaturamento = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefoneFaturamento = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 30])]
    private int $prazoGlosaDias = 30;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacoes = null;

    #[ORM\Column]
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

    public function getRegistroAns(): ?string
    {
        return $this->registroAns;
    }

    public function setRegistroAns(?string $registroAns): static
    {
        $this->registroAns = $registroAns;

        return $this;
    }

    public function getCnpj(): ?string
    {
        return $this->cnpj;
    }

    public function setCnpj(?string $cnpj): static
    {
        $this->cnpj = $cnpj;

        return $this;
    }

    public function getCodigoPrestador(): ?string
    {
        return $this->codigoPrestador;
    }

    public function setCodigoPrestador(?string $codigoPrestador): static
    {
        $this->codigoPrestador = $codigoPrestador;

        return $this;
    }

    public function getVersaoTiss(): ?string
    {
        return $this->versaoTiss;
    }

    public function setVersaoTiss(?string $versaoTiss): static
    {
        $this->versaoTiss = $versaoTiss;

        return $this;
    }

    public function getContatoFaturamento(): ?string
    {
        return $this->contatoFaturamento;
    }

    public function setContatoFaturamento(?string $contatoFaturamento): static
    {
        $this->contatoFaturamento = $contatoFaturamento;

        return $this;
    }

    public function getEmailFaturamento(): ?string
    {
        return $this->emailFaturamento;
    }

    public function setEmailFaturamento(?string $emailFaturamento): static
    {
        $this->emailFaturamento = $emailFaturamento;

        return $this;
    }

    public function getTelefoneFaturamento(): ?string
    {
        return $this->telefoneFaturamento;
    }

    public function setTelefoneFaturamento(?string $telefoneFaturamento): static
    {
        $this->telefoneFaturamento = $telefoneFaturamento;

        return $this;
    }

    public function getPrazoGlosaDias(): int
    {
        return $this->prazoGlosaDias;
    }

    public function setPrazoGlosaDias(int $prazoGlosaDias): static
    {
        $this->prazoGlosaDias = $prazoGlosaDias;

        return $this;
    }

    public function getObservacoes(): ?string
    {
        return $this->observacoes;
    }

    public function setObservacoes(?string $observacoes): static
    {
        $this->observacoes = $observacoes;

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
