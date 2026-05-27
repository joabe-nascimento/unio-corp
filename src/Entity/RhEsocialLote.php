<?php

namespace App\Entity;

use App\Repository\RhEsocialLoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhEsocialLoteRepository::class)]
#[ORM\Table(name: 'rh_esocial_lote')]
class RhEsocialLote
{
    public const STATUS_PENDENTE = 'PENDENTE';
    public const STATUS_PROCESSANDO = 'PROCESSANDO';
    public const STATUS_ENVIADO = 'ENVIADO';
    public const STATUS_ERRO = 'ERRO';

    public const MAX_TENTATIVAS = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 7)]
    private string $referencia;

    #[ORM\Column(length: 16)]
    private string $tipoEvento;

    #[ORM\Column(length: 24)]
    private string $status;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $protocolo = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $enviadoEm = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $tentativas = 0;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ultimoErro = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getReferencia(): string { return $this->referencia; }
    public function setReferencia(string $referencia): static { $this->referencia = $referencia; return $this; }

    public function getTipoEvento(): string { return $this->tipoEvento; }
    public function setTipoEvento(string $tipoEvento): static { $this->tipoEvento = $tipoEvento; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getProtocolo(): ?string { return $this->protocolo; }
    public function setProtocolo(?string $protocolo): static { $this->protocolo = $protocolo; return $this; }

    public function getPayload(): ?array { return $this->payload; }
    public function setPayload(?array $payload): static { $this->payload = $payload; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function getEnviadoEm(): ?\DateTimeImmutable { return $this->enviadoEm; }

    public function setEnviadoEm(?\DateTimeImmutable $enviadoEm): static { $this->enviadoEm = $enviadoEm; return $this; }

    public function getTentativas(): int { return $this->tentativas; }

    public function setTentativas(int $tentativas): static { $this->tentativas = $tentativas; return $this; }

    public function getUltimoErro(): ?string { return $this->ultimoErro; }

    public function setUltimoErro(?string $ultimoErro): static { $this->ultimoErro = $ultimoErro; return $this; }

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_ERRO && $this->tentativas < self::MAX_TENTATIVAS;
    }
}
