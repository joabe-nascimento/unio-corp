<?php

namespace App\Entity;

use App\Repository\ClinicEmpresaConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicEmpresaConfigRepository::class)]
#[ORM\Table(name: 'clinic_empresa_config')]
class ClinicEmpresaConfig
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $produtos = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $guias = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $integracoes = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $branding = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $planosLimites = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $onboarding = [];

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getProdutos(): array
    {
        return $this->produtos;
    }

    /** @param array<string, mixed> $produtos */
    public function setProdutos(array $produtos): static
    {
        $this->produtos = $produtos;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getGuias(): array
    {
        return $this->guias;
    }

    /** @param array<string, mixed> $guias */
    public function setGuias(array $guias): static
    {
        $this->guias = $guias;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getIntegracoes(): array
    {
        return $this->integracoes;
    }

    /** @param array<string, mixed> $integracoes */
    public function setIntegracoes(array $integracoes): static
    {
        $this->integracoes = $integracoes;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getBranding(): array
    {
        return $this->branding;
    }

    /** @param array<string, mixed> $branding */
    public function setBranding(array $branding): static
    {
        $this->branding = $branding;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getPlanosLimites(): array
    {
        return $this->planosLimites;
    }

    /** @param array<string, mixed> $planosLimites */
    public function setPlanosLimites(array $planosLimites): static
    {
        $this->planosLimites = $planosLimites;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getOnboarding(): array
    {
        return $this->onboarding;
    }

    /** @param array<string, mixed> $onboarding */
    public function setOnboarding(array $onboarding): static
    {
        $this->onboarding = $onboarding;

        return $this;
    }
}
