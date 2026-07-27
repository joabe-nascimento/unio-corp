<?php

namespace App\Entity;

use App\Repository\JuridicoProcessoParteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoProcessoParteRepository::class)]
#[ORM\Table(name: 'juridico_processo_parte')]
#[ORM\Index(columns: ['processo_id'], name: 'IDX_JUR_PARTE_PROCESSO')]
class JuridicoProcessoParte
{
    public const TIPO_AUTOR = 'autor';
    public const TIPO_REU = 'reu';
    public const TIPO_TERCEIRO = 'terceiro';
    public const TIPO_TESTEMUNHA = 'testemunha';
    public const TIPO_PERITO = 'perito';
    public const TIPO_OUTRO = 'outro';

    public const TIPOS = [
        self::TIPO_AUTOR,
        self::TIPO_REU,
        self::TIPO_TERCEIRO,
        self::TIPO_TESTEMUNHA,
        self::TIPO_PERITO,
        self::TIPO_OUTRO,
    ];

    public const POLO_ATIVO = 'ativo';
    public const POLO_PASSIVO = 'passivo';
    public const POLO_OUTRO = 'outro';

    public const POLOS = [self::POLO_ATIVO, self::POLO_PASSIVO, self::POLO_OUTRO];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JuridicoProcesso $processo;

    #[ORM\Column(length: 180)]
    private string $nome;

    #[ORM\Column(length: 20)]
    private string $tipo = self::TIPO_OUTRO;

    #[ORM\Column(length: 10)]
    private string $polo = self::POLO_OUTRO;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $documento = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $advogado = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $oab = null;

    #[ORM\Column]
    private bool $principal = false;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProcesso(): JuridicoProcesso { return $this->processo; }
    public function setProcesso(JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getNome(): string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }
    public function getPolo(): string { return $this->polo; }
    public function setPolo(string $polo): static { $this->polo = $polo; return $this; }
    public function getDocumento(): ?string { return $this->documento; }
    public function setDocumento(?string $documento): static { $this->documento = $documento; return $this; }
    public function getAdvogado(): ?string { return $this->advogado; }
    public function setAdvogado(?string $advogado): static { $this->advogado = $advogado; return $this; }
    public function getOab(): ?string { return $this->oab; }
    public function setOab(?string $oab): static { $this->oab = $oab; return $this; }
    public function isPrincipal(): bool { return $this->principal; }
    public function setPrincipal(bool $principal): static { $this->principal = $principal; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
