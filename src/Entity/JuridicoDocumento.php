<?php

namespace App\Entity;

use App\Repository\JuridicoDocumentoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoDocumentoRepository::class)]
#[ORM\Table(name: 'juridico_documento')]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_JUR_DOCUMENTO_EMPRESA')]
class JuridicoDocumento
{
    public const CATEGORIA_PETICAO = 'peticao';
    public const CATEGORIA_PECA_PROCESSUAL = 'peca_processual';
    public const CATEGORIA_CONTRATO = 'contrato';
    public const CATEGORIA_PARECER = 'parecer';
    public const CATEGORIA_PROCURACAO = 'procuracao';
    public const CATEGORIA_OUTRO = 'outro';

    public const CATEGORIAS = [
        self::CATEGORIA_PETICAO,
        self::CATEGORIA_PECA_PROCESSUAL,
        self::CATEGORIA_CONTRATO,
        self::CATEGORIA_PARECER,
        self::CATEGORIA_PROCURACAO,
        self::CATEGORIA_OUTRO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoProcesso $processo = null;

    #[ORM\Column(length: 200)]
    private string $nome;

    #[ORM\Column(length: 24)]
    private string $categoria = self::CATEGORIA_OUTRO;

    #[ORM\Column(length: 255)]
    private string $arquivoPath;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private int $tamanhoBytes = 0;

    #[ORM\Column]
    private bool $confidencial = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getProcesso(): ?JuridicoProcesso { return $this->processo; }
    public function setProcesso(?JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getNome(): string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }
    public function getCategoria(): string { return $this->categoria; }
    public function setCategoria(string $categoria): static { $this->categoria = $categoria; return $this; }
    public function getArquivoPath(): string { return $this->arquivoPath; }
    public function setArquivoPath(string $arquivoPath): static { $this->arquivoPath = $arquivoPath; return $this; }
    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): static { $this->mimeType = $mimeType; return $this; }
    public function getTamanhoBytes(): int { return $this->tamanhoBytes; }
    public function setTamanhoBytes(int $tamanhoBytes): static { $this->tamanhoBytes = $tamanhoBytes; return $this; }
    public function isConfidencial(): bool { return $this->confidencial; }
    public function setConfidencial(bool $confidencial): static { $this->confidencial = $confidencial; return $this; }
    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $uploadedBy): static { $this->uploadedBy = $uploadedBy; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function getTamanhoFormatado(): string
    {
        $bytes = $this->tamanhoBytes;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
