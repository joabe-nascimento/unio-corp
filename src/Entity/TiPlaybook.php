<?php

namespace App\Entity;

use App\Repository\TiPlaybookRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiPlaybookRepository::class)]
#[ORM\Table(name: 'ti_playbook')]
class TiPlaybook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(length: 120)]
    private string $gatilho;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $categoria = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $prioridade = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $passos = [];

    #[ORM\Column(type: 'boolean')]
    private bool $ativo = true;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'title' => $this->titulo,
            'titulo' => $this->titulo,
            'trigger' => $this->gatilho,
            'gatilho' => $this->gatilho,
            'category' => $this->categoria,
            'categoria' => $this->categoria,
            'priority' => $this->prioridade,
            'prioridade' => $this->prioridade,
            'steps' => $this->passos,
            'passos' => $this->passos,
            'active' => $this->ativo,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getGatilho(): string { return $this->gatilho; }
    public function setGatilho(string $gatilho): static { $this->gatilho = $gatilho; return $this; }
    public function getCategoria(): ?string { return $this->categoria; }
    public function setCategoria(?string $categoria): static { $this->categoria = $categoria; return $this; }
    public function getPrioridade(): ?string { return $this->prioridade; }
    public function setPrioridade(?string $prioridade): static { $this->prioridade = $prioridade; return $this; }
    /** @return list<string> */
    public function getPassos(): array { return $this->passos; }
    /** @param list<string> $passos */
    public function setPassos(array $passos): static { $this->passos = $passos; return $this; }
    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }
}
