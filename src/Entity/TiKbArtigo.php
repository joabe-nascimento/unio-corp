<?php

namespace App\Entity;

use App\Repository\TiKbArtigoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiKbArtigoRepository::class)]
#[ORM\Table(name: 'ti_kb_artigo')]
#[ORM\UniqueConstraint(name: 'UNIQ_TI_KB_CODIGO', fields: ['empresa', 'codigo'])]
class TiKbArtigo
{
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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $conteudo = null;

    #[ORM\Column(length: 32)]
    private string $categoria = 'geral';

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $tags = [];

    #[ORM\Column]
    private int $visualizacoes = 0;

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
            'title' => $this->titulo,
            'titulo' => $this->titulo,
            'summary' => $this->resumo,
            'resumo' => $this->resumo,
            'content' => $this->conteudo,
            'conteudo' => $this->conteudo,
            'category' => $this->categoria,
            'categoria' => $this->categoria,
            'tags' => $this->tags,
            'views' => $this->visualizacoes,
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
    public function getConteudo(): ?string { return $this->conteudo; }
    public function setConteudo(?string $conteudo): static { $this->conteudo = $conteudo; return $this; }
    public function getCategoria(): string { return $this->categoria; }
    public function setCategoria(string $categoria): static { $this->categoria = $categoria; return $this; }
    /** @return list<string> */
    public function getTags(): array { return $this->tags; }
    /** @param list<string> $tags */
    public function setTags(array $tags): static { $this->tags = $tags; return $this; }
    public function getVisualizacoes(): int { return $this->visualizacoes; }
    public function incrementVisualizacoes(): static { ++$this->visualizacoes; return $this; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }
}
