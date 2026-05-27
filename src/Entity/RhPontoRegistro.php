<?php

namespace App\Entity;

use App\Repository\RhPontoRegistroRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhPontoRegistroRepository::class)]
#[ORM\Table(name: 'rh_ponto_registro')]
class RhPontoRegistro
{
    public const TIPO_ENTRADA = 'ENTRADA';
    public const TIPO_SAIDA = 'SAIDA';

    public const ORIGEM_WEB = 'WEB';
    public const ORIGEM_MOBILE = 'MOBILE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: Funcionario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Funcionario $funcionario;

    #[ORM\Column(length: 16)]
    private string $tipo;

    #[ORM\Column]
    private \DateTimeImmutable $registradoEm;

    #[ORM\Column(length: 24)]
    private string $origem;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observacao = null;

    public function __construct()
    {
        $this->registradoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getFuncionario(): Funcionario { return $this->funcionario; }
    public function setFuncionario(Funcionario $funcionario): static { $this->funcionario = $funcionario; return $this; }

    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }

    public function getRegistradoEm(): \DateTimeImmutable { return $this->registradoEm; }
    public function setRegistradoEm(\DateTimeImmutable $registradoEm): static { $this->registradoEm = $registradoEm; return $this; }

    public function getOrigem(): string { return $this->origem; }
    public function setOrigem(string $origem): static { $this->origem = $origem; return $this; }

    public function getObservacao(): ?string { return $this->observacao; }
    public function setObservacao(?string $observacao): static { $this->observacao = $observacao; return $this; }
}
