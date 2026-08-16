<?php

namespace App\Entity;

use App\Repository\JuridicoPublicacaoEventoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoPublicacaoEventoRepository::class)]
#[ORM\Table(name: 'juridico_publicacao_evento')]
#[ORM\Index(columns: ['publicacao_id'], name: 'IDX_JUR_PUB_EVT_PUB')]
class JuridicoPublicacaoEvento
{
    public const TIPO_TRIAGEM = 'triagem';
    public const TIPO_MATCH = 'match';
    public const TIPO_PRAZO = 'prazo';
    public const TIPO_ALERTA = 'alerta';
    public const TIPO_ERRO = 'erro';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JuridicoPublicacao::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JuridicoPublicacao $publicacao;

    #[ORM\Column(length: 32)]
    private string $tipo;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPublicacao(): JuridicoPublicacao { return $this->publicacao; }
    public function setPublicacao(JuridicoPublicacao $publicacao): static { $this->publicacao = $publicacao; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }
    /** @return array<string, mixed>|null */
    public function getPayload(): ?array { return $this->payload; }
    /** @param array<string, mixed>|null $payload */
    public function setPayload(?array $payload): static { $this->payload = $payload; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
