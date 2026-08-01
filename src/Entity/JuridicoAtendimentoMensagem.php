<?php

namespace App\Entity;

use App\Repository\JuridicoAtendimentoMensagemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoAtendimentoMensagemRepository::class)]
#[ORM\Table(name: 'juridico_atendimento_mensagem')]
#[ORM\Index(columns: ['ticket_id', 'criado_em'], name: 'IDX_JUR_ATEND_MSG_TICKET')]
class JuridicoAtendimentoMensagem
{
    public const DIRECAO_ENTRADA = 'entrada';
    public const DIRECAO_SAIDA = 'saida';
    public const DIRECAO_INTERNO = 'interno';

    public const DIRECOES = [self::DIRECAO_ENTRADA, self::DIRECAO_SAIDA, self::DIRECAO_INTERNO];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JuridicoAtendimentoTicket::class, inversedBy: 'mensagens')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JuridicoAtendimentoTicket $ticket;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $autor = null;

    #[ORM\Column(length: 12)]
    private string $direcao;

    #[ORM\Column(length: 16)]
    private string $canal;

    #[ORM\Column(type: 'text')]
    private string $corpo;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $remetenteNome = null;

    #[ORM\Column]
    private bool $whatsappEnviado = false;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $whatsappStatus = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTicket(): JuridicoAtendimentoTicket { return $this->ticket; }
    public function setTicket(JuridicoAtendimentoTicket $ticket): static { $this->ticket = $ticket; return $this; }
    public function getAutor(): ?User { return $this->autor; }
    public function setAutor(?User $autor): static { $this->autor = $autor; return $this; }
    public function getDirecao(): string { return $this->direcao; }
    public function setDirecao(string $direcao): static { $this->direcao = $direcao; return $this; }
    public function getCanal(): string { return $this->canal; }
    public function setCanal(string $canal): static { $this->canal = $canal; return $this; }
    public function getCorpo(): string { return $this->corpo; }
    public function setCorpo(string $corpo): static { $this->corpo = $corpo; return $this; }
    public function getRemetenteNome(): ?string { return $this->remetenteNome; }
    public function setRemetenteNome(?string $remetenteNome): static { $this->remetenteNome = $remetenteNome; return $this; }
    public function isWhatsappEnviado(): bool { return $this->whatsappEnviado; }
    public function setWhatsappEnviado(bool $whatsappEnviado): static { $this->whatsappEnviado = $whatsappEnviado; return $this; }
    public function getWhatsappStatus(): ?string { return $this->whatsappStatus; }
    public function setWhatsappStatus(?string $whatsappStatus): static { $this->whatsappStatus = $whatsappStatus; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
