<?php

namespace App\Entity;

use App\Repository\ClinicDocumentoEmissaoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicDocumentoEmissaoRepository::class)]
#[ORM\Table(name: 'clinic_documento_emissao')]
class ClinicDocumentoEmissao
{
    public const TIPO_CARTEIRINHA = 'carteirinha';
    public const TIPO_COMPROVANTE = 'comprovante';

    public const ACAO_EMITIR = 'emitir';
    public const ACAO_REEMITIR = 'reemitir';
    public const ACAO_REVOGAR = 'revogar';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PosOperatorioPaciente $paciente;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $emitidoPor = null;

    #[ORM\Column(length: 24)]
    private string $tipo = self::TIPO_CARTEIRINHA;

    #[ORM\Column(length: 32)]
    private string $codigoVerificacao = '';

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $plano = null;

    #[ORM\Column(length: 16)]
    private string $acao = self::ACAO_EMITIR;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $hashDocumento = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $meta = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

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

    public function getPaciente(): PosOperatorioPaciente
    {
        return $this->paciente;
    }

    public function setPaciente(PosOperatorioPaciente $paciente): static
    {
        $this->paciente = $paciente;

        return $this;
    }

    public function getEmitidoPor(): ?User
    {
        return $this->emitidoPor;
    }

    public function setEmitidoPor(?User $emitidoPor): static
    {
        $this->emitidoPor = $emitidoPor;

        return $this;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getCodigoVerificacao(): string
    {
        return $this->codigoVerificacao;
    }

    public function setCodigoVerificacao(string $codigoVerificacao): static
    {
        $this->codigoVerificacao = strtoupper(trim($codigoVerificacao));

        return $this;
    }

    public function getPlano(): ?string
    {
        return $this->plano;
    }

    public function setPlano(?string $plano): static
    {
        $this->plano = $plano;

        return $this;
    }

    public function getAcao(): string
    {
        return $this->acao;
    }

    public function setAcao(string $acao): static
    {
        $this->acao = $acao;

        return $this;
    }

    public function getHashDocumento(): ?string
    {
        return $this->hashDocumento;
    }

    public function setHashDocumento(?string $hashDocumento): static
    {
        $this->hashDocumento = $hashDocumento;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /** @param array<string, mixed> $meta */
    public function setMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
