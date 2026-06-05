<?php

namespace App\Entity;

use App\Repository\IntegDeadLetterRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegDeadLetterRepository::class)]
#[ORM\Table(name: 'integ_dead_letter')]
class IntegDeadLetter
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_RETRY = 'retry';
    public const STATUS_RESOLVIDO = 'resolvido';
    public const STATUS_DESCARTADO = 'descartado';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: IntegConector::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?IntegConector $conector = null;

    #[ORM\Column(length: 120)]
    private string $evento;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'text')]
    private string $erroMensagem;

    #[ORM\Column(type: 'smallint')]
    private int $tentativas = 1;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_PENDENTE;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $proximaRetryEm = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $chamadoTiCodigo = null;

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
            'id' => $this->id,
            'conector' => $this->conector?->getNome() ?? '—',
            'evento' => $this->evento,
            'payload_preview' => mb_substr(json_encode($this->payload), 0, 80) . '...',
            'payload' => $this->payload,
            'erro' => $this->erroMensagem,
            'tentativas' => $this->tentativas,
            'status' => $this->status,
            'proxima_retry' => $this->proximaRetryEm?->format('d/m H:i'),
            'chamado_ti' => $this->chamadoTiCodigo,
            'criado_em' => $this->criadoEm->format('d/m/Y H:i'),
        ];
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }

    public function setEmpresa(Empresa $e): static { $this->empresa = $e; return $this; }

    public function getConector(): ?IntegConector { return $this->conector; }

    public function setConector(?IntegConector $c): static { $this->conector = $c; return $this; }

    public function getEvento(): string { return $this->evento; }

    public function setEvento(string $e): static { $this->evento = $e; return $this; }

    public function getPayload(): array { return $this->payload; }

    public function setPayload(array $p): static { $this->payload = $p; return $this; }

    public function getErroMensagem(): string { return $this->erroMensagem; }

    public function setErroMensagem(string $e): static { $this->erroMensagem = $e; return $this; }

    public function getTentativas(): int { return $this->tentativas; }

    public function setTentativas(int $t): static { $this->tentativas = $t; return $this; }

    public function getStatus(): string { return $this->status; }

    public function setStatus(string $s): static { $this->status = $s; return $this; }

    public function getProximaRetryEm(): ?\DateTimeImmutable { return $this->proximaRetryEm; }

    public function setProximaRetryEm(?\DateTimeImmutable $d): static { $this->proximaRetryEm = $d; return $this; }

    public function getChamadoTiCodigo(): ?string { return $this->chamadoTiCodigo; }

    public function setChamadoTiCodigo(?string $c): static { $this->chamadoTiCodigo = $c; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }

    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }
}
