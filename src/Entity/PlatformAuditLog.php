<?php

namespace App\Entity;

use App\Repository\PlatformAuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlatformAuditLogRepository::class)]
#[ORM\Table(name: 'platform_audit_log')]
#[ORM\Index(name: 'idx_platform_audit_criado', columns: ['criado_em'])]
#[ORM\Index(name: 'idx_platform_audit_category', columns: ['categoria', 'criado_em'])]
class PlatformAuditLog
{
    public const CATEGORY_ADMIN = 'admin';
    public const CATEGORY_AUTH = 'auth';
    public const CATEGORY_DEPLOY = 'deploy';
    public const CATEGORY_HTTP = 'http';
    public const CATEGORY_CONFIG = 'config';
    public const CATEGORY_SYSTEM = 'system';

    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_ACTIVATE = 'activate';
    public const ACTION_DEACTIVATE = 'deactivate';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGIN_FAILED = 'login_failed';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_DEPLOY = 'deploy';
    public const ACTION_SAVE = 'save';
    public const ACTION_ERROR = 'error';

    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_FAILURE = 'failure';
    public const OUTCOME_WARNING = 'warning';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actorEmail = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $actorNome = null;

    #[ORM\Column(length: 24)]
    private string $categoria = self::CATEGORY_SYSTEM;

    #[ORM\Column(length: 32)]
    private string $acao = self::ACTION_ERROR;

    #[ORM\Column(length: 16)]
    private string $resultado = self::OUTCOME_SUCCESS;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $alvoTipo = null;

    #[ORM\Column(nullable: true)]
    private ?int $alvoId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alvoRotulo = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $rota = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: 'text')]
    private string $mensagem = '';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): static
    {
        $this->actor = $actor;
        if ($actor !== null) {
            $this->actorEmail = $actor->getEmail();
            $this->actorNome = $actor->getNome();
        }

        return $this;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function setActorEmail(?string $actorEmail): static
    {
        $this->actorEmail = $actorEmail;

        return $this;
    }

    public function getActorNome(): ?string
    {
        return $this->actorNome;
    }

    public function setActorNome(?string $actorNome): static
    {
        $this->actorNome = $actorNome;

        return $this;
    }

    public function getCategoria(): string
    {
        return $this->categoria;
    }

    public function setCategoria(string $categoria): static
    {
        $this->categoria = $categoria;

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

    public function getResultado(): string
    {
        return $this->resultado;
    }

    public function setResultado(string $resultado): static
    {
        $this->resultado = $resultado;

        return $this;
    }

    public function getAlvoTipo(): ?string
    {
        return $this->alvoTipo;
    }

    public function setAlvoTipo(?string $alvoTipo): static
    {
        $this->alvoTipo = $alvoTipo;

        return $this;
    }

    public function getAlvoId(): ?int
    {
        return $this->alvoId;
    }

    public function setAlvoId(?int $alvoId): static
    {
        $this->alvoId = $alvoId;

        return $this;
    }

    public function getAlvoRotulo(): ?string
    {
        return $this->alvoRotulo;
    }

    public function setAlvoRotulo(?string $alvoRotulo): static
    {
        $this->alvoRotulo = $alvoRotulo;

        return $this;
    }

    public function getRota(): ?string
    {
        return $this->rota;
    }

    public function setRota(?string $rota): static
    {
        $this->rota = $rota;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    public function getMensagem(): string
    {
        return $this->mensagem;
    }

    public function setMensagem(string $mensagem): static
    {
        $this->mensagem = $mensagem;

        return $this;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'at' => $this->criadoEm->format('c'),
            'actor_email' => $this->actorEmail,
            'actor_nome' => $this->actorNome,
            'categoria' => $this->categoria,
            'acao' => $this->acao,
            'resultado' => $this->resultado,
            'alvo_tipo' => $this->alvoTipo,
            'alvo_id' => $this->alvoId,
            'alvo_rotulo' => $this->alvoRotulo,
            'rota' => $this->rota,
            'ip' => $this->ip,
            'mensagem' => $this->mensagem,
        ];
    }
}
