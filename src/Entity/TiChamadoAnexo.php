<?php

namespace App\Entity;

use App\Repository\TiChamadoAnexoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiChamadoAnexoRepository::class)]
#[ORM\Table(name: 'ti_chamado_anexo')]
class TiChamadoAnexo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TiChamado::class, inversedBy: 'anexos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TiChamado $chamado;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $enviadoPor = null;

    #[ORM\Column(length: 255)]
    private string $nomeOriginal;

    #[ORM\Column(length: 255)]
    private string $caminho;

    #[ORM\Column(length: 120)]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    private int $tamanho;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $chamadoCodigo = $this->chamado->getCodigo();

        return [
            'id' => $this->id,
            'name' => $this->nomeOriginal,
            'url' => $this->caminho,
            'download_url' => '/ti/chamados/' . $chamadoCodigo . '/anexo/' . $this->id,
            'mime' => $this->mimeType,
            'size' => $this->tamanho,
            'size_label' => self::formatSize($this->tamanho),
            'is_image' => str_starts_with($this->mimeType, 'image/'),
            'created_at' => $this->criadoEm->format('d/m/Y H:i'),
        ];
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChamado(): TiChamado
    {
        return $this->chamado;
    }

    public function setChamado(TiChamado $chamado): static
    {
        $this->chamado = $chamado;

        return $this;
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

    public function getEnviadoPor(): ?User
    {
        return $this->enviadoPor;
    }

    public function setEnviadoPor(?User $enviadoPor): static
    {
        $this->enviadoPor = $enviadoPor;

        return $this;
    }

    public function getNomeOriginal(): string
    {
        return $this->nomeOriginal;
    }

    public function setNomeOriginal(string $nomeOriginal): static
    {
        $this->nomeOriginal = $nomeOriginal;

        return $this;
    }

    public function getCaminho(): string
    {
        return $this->caminho;
    }

    public function setCaminho(string $caminho): static
    {
        $this->caminho = $caminho;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getTamanho(): int
    {
        return $this->tamanho;
    }

    public function setTamanho(int $tamanho): static
    {
        $this->tamanho = $tamanho;

        return $this;
    }
}
