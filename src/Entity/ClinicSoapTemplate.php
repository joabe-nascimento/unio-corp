<?php

namespace App\Entity;

use App\Repository\ClinicSoapTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicSoapTemplateRepository::class)]
#[ORM\Table(name: 'clinic_soap_template')]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_CLINIC_SOAP_TEMPLATE_EMPRESA')]
class ClinicSoapTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 120)]
    private string $nome = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $procedimentoTipo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $queixa = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $exame = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $hipotese = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $conduta = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $cid10Sugerido = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $ativo = true;

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

    public function getProcedimentoTipo(): ?string
    {
        return $this->procedimentoTipo;
    }

    public function setProcedimentoTipo(?string $procedimentoTipo): static
    {
        $this->procedimentoTipo = $procedimentoTipo;

        return $this;
    }

    public function getQueixa(): ?string
    {
        return $this->queixa;
    }

    public function setQueixa(?string $queixa): static
    {
        $this->queixa = $queixa;

        return $this;
    }

    public function getExame(): ?string
    {
        return $this->exame;
    }

    public function setExame(?string $exame): static
    {
        $this->exame = $exame;

        return $this;
    }

    public function getHipotese(): ?string
    {
        return $this->hipotese;
    }

    public function setHipotese(?string $hipotese): static
    {
        $this->hipotese = $hipotese;

        return $this;
    }

    public function getConduta(): ?string
    {
        return $this->conduta;
    }

    public function setConduta(?string $conduta): static
    {
        $this->conduta = $conduta;

        return $this;
    }

    public function getCid10Sugerido(): ?string
    {
        return $this->cid10Sugerido;
    }

    public function setCid10Sugerido(?string $cid10Sugerido): static
    {
        $this->cid10Sugerido = $cid10Sugerido;

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
}
