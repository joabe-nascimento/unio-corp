<?php

namespace App\Entity;

use App\Repository\RhCandidatoAprovacaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhCandidatoAprovacaoRepository::class)]
#[ORM\Table(name: 'rh_candidato_aprovacao')]
class RhCandidatoAprovacao
{
    public const STATUS_PENDENTE = 'PENDENTE';
    public const STATUS_APROVADO = 'APROVADO';
    public const STATUS_REJEITADO = 'REJEITADO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RhCandidato::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RhCandidato $candidato;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $solicitante;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $aprovador = null;

    #[ORM\Column(length: 32)]
    private string $etapaDestino;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_PENDENTE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comentario = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $decididoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCandidato(): RhCandidato { return $this->candidato; }
    public function setCandidato(RhCandidato $candidato): static { $this->candidato = $candidato; return $this; }
    public function getSolicitante(): User { return $this->solicitante; }
    public function setSolicitante(User $solicitante): static { $this->solicitante = $solicitante; return $this; }
    public function getAprovador(): ?User { return $this->aprovador; }
    public function setAprovador(?User $aprovador): static { $this->aprovador = $aprovador; return $this; }
    public function getEtapaDestino(): string { return $this->etapaDestino; }
    public function setEtapaDestino(string $etapaDestino): static { $this->etapaDestino = $etapaDestino; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APROVADO => 'Aprovado',
            self::STATUS_REJEITADO => 'Rejeitado',
            default => 'Pendente',
        };
    }

    public function getStatusClass(): string
    {
        return match ($this->status) {
            self::STATUS_APROVADO => 'success',
            self::STATUS_REJEITADO => 'secondary',
            default => 'warning',
        };
    }
    public function getComentario(): ?string { return $this->comentario; }
    public function setComentario(?string $comentario): static { $this->comentario = $comentario; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getDecididoEm(): ?\DateTimeImmutable { return $this->decididoEm; }
    public function setDecididoEm(?\DateTimeImmutable $decididoEm): static { $this->decididoEm = $decididoEm; return $this; }
}
