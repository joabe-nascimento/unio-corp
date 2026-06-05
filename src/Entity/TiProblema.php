<?php

namespace App\Entity;

use App\Repository\TiProblemaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiProblemaRepository::class)]
#[ORM\Table(name: 'ti_problema')]
#[ORM\UniqueConstraint(name: 'UNIQ_TI_PROBLEMA_CODIGO', fields: ['empresa', 'codigo'])]
class TiProblema
{
    public const STATUS_ABERTO = 'aberto';
    public const STATUS_INVESTIGACAO = 'investigacao';
    public const STATUS_RESOLVIDO = 'resolvido';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 16)]
    private string $codigo = '';

    #[ORM\Column(length: 200)]
    private string $titulo;

    #[ORM\Column(type: 'text')]
    private string $resumo;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ABERTO;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $causaRaiz = null;

    #[ORM\Column(length: 4)]
    private string $prioridade = 'P3';

    #[ORM\Column(length: 32)]
    private string $categoria = 'sistema';

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
    public function toArray(int $ticketCount = 0): array
    {
        return [
            'db_id' => $this->id,
            'id' => $this->codigo,
            'codigo' => $this->codigo,
            'title' => $this->titulo,
            'titulo' => $this->titulo,
            'summary' => $this->resumo,
            'resumo' => $this->resumo,
            'status' => $this->status,
            'root_cause' => $this->causaRaiz,
            'causa_raiz' => $this->causaRaiz,
            'priority' => $this->prioridade,
            'prioridade' => $this->prioridade,
            'category' => $this->categoria,
            'categoria' => $this->categoria,
            'ticket_count' => $ticketCount,
            'created_at' => $this->criadoEm->format('d/m/Y'),
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getCodigo(): string { return $this->codigo; }
    public function setCodigo(string $codigo): static { $this->codigo = $codigo; return $this; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getResumo(): string { return $this->resumo; }
    public function setResumo(string $resumo): static { $this->resumo = $resumo; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getCausaRaiz(): ?string { return $this->causaRaiz; }
    public function setCausaRaiz(?string $causaRaiz): static { $this->causaRaiz = $causaRaiz; return $this; }
    public function getPrioridade(): string { return $this->prioridade; }
    public function setPrioridade(string $prioridade): static { $this->prioridade = $prioridade; return $this; }
    public function getCategoria(): string { return $this->categoria; }
    public function setCategoria(string $categoria): static { $this->categoria = $categoria; return $this; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }
}
