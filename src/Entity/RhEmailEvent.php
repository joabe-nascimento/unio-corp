<?php

namespace App\Entity;

use App\Repository\RhEmailEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhEmailEventRepository::class)]
#[ORM\Table(name: 'rh_email_event')]
class RhEmailEvent
{
    public const STATUS_PENDENTE = 'PENDENTE';
    public const STATUS_ENVIADO = 'ENVIADO';
    public const STATUS_ERRO = 'ERRO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 180)]
    private string $destinatario;

    #[ORM\Column(length: 200)]
    private string $assunto;

    #[ORM\Column(length: 64)]
    private string $template;

    #[ORM\Column(length: 24)]
    private string $status;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $enviadoEm = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getDestinatario(): string { return $this->destinatario; }
    public function setDestinatario(string $destinatario): static { $this->destinatario = $destinatario; return $this; }

    public function getAssunto(): string { return $this->assunto; }
    public function setAssunto(string $assunto): static { $this->assunto = $assunto; return $this; }

    public function getTemplate(): string { return $this->template; }
    public function setTemplate(string $template): static { $this->template = $template; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getPayload(): ?array { return $this->payload; }
    public function setPayload(?array $payload): static { $this->payload = $payload; return $this; }

    public function getEnviadoEm(): ?\DateTimeImmutable { return $this->enviadoEm; }
    public function setEnviadoEm(?\DateTimeImmutable $enviadoEm): static { $this->enviadoEm = $enviadoEm; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
