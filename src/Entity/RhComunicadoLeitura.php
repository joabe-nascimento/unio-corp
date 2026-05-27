<?php

namespace App\Entity;

use App\Repository\RhComunicadoLeituraRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhComunicadoLeituraRepository::class)]
#[ORM\Table(name: 'rh_comunicado_leitura')]
#[ORM\UniqueConstraint(name: 'UNIQ_RH_COM_LEITURA', fields: ['comunicado', 'funcionario'])]
class RhComunicadoLeitura
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RhComunicado::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RhComunicado $comunicado;

    #[ORM\ManyToOne(targetEntity: Funcionario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Funcionario $funcionario;

    #[ORM\Column]
    private \DateTimeImmutable $lidoEm;

    public function __construct()
    {
        $this->lidoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getComunicado(): RhComunicado { return $this->comunicado; }
    public function setComunicado(RhComunicado $comunicado): static { $this->comunicado = $comunicado; return $this; }

    public function getFuncionario(): Funcionario { return $this->funcionario; }
    public function setFuncionario(Funcionario $funcionario): static { $this->funcionario = $funcionario; return $this; }

    public function getLidoEm(): \DateTimeImmutable { return $this->lidoEm; }
    public function setLidoEm(\DateTimeImmutable $lidoEm): static { $this->lidoEm = $lidoEm; return $this; }
}
