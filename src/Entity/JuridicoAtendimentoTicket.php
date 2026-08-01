<?php

namespace App\Entity;

use App\Repository\JuridicoAtendimentoTicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoAtendimentoTicketRepository::class)]
#[ORM\Table(name: 'juridico_atendimento_ticket')]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'IDX_JUR_ATEND_TKT_EMPRESA_STATUS')]
#[ORM\Index(columns: ['empresa_id', 'sla_limite_em'], name: 'IDX_JUR_ATEND_TKT_SLA')]
class JuridicoAtendimentoTicket
{
    public const STATUS_ABERTO = 'aberto';
    public const STATUS_EM_ATENDIMENTO = 'em_atendimento';
    public const STATUS_AGUARDANDO_CLIENTE = 'aguardando_cliente';
    public const STATUS_RESOLVIDO = 'resolvido';

    public const STATUSES = [
        self::STATUS_ABERTO,
        self::STATUS_EM_ATENDIMENTO,
        self::STATUS_AGUARDANDO_CLIENTE,
        self::STATUS_RESOLVIDO,
    ];

    public const CANAL_WHATSAPP = 'whatsapp';
    public const CANAL_EMAIL = 'email';
    public const CANAL_INTERNO = 'interno';

    public const CANAIS = [self::CANAL_WHATSAPP, self::CANAL_EMAIL, self::CANAL_INTERNO];

    public const PRIORIDADE_NORMAL = 'normal';
    public const PRIORIDADE_ALTA = 'alta';

    public const SLA_PREMIUM_HORAS = 4;
    public const SLA_STANDARD_HORAS = 24;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: JuridicoCliente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoCliente $cliente = null;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoProcesso $processo = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsavel = null;

    #[ORM\Column(length: 200)]
    private string $assunto;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ABERTO;

    #[ORM\Column(length: 16)]
    private string $canal = self::CANAL_INTERNO;

    #[ORM\Column(length: 16)]
    private string $prioridade = self::PRIORIDADE_NORMAL;

    #[ORM\Column]
    private int $slaHoras = self::SLA_STANDARD_HORAS;

    #[ORM\Column]
    private \DateTimeImmutable $slaLimiteEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $primeiraRespostaEm = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvidoEm = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $atualizadoEm = null;

    /** @var Collection<int, JuridicoAtendimentoMensagem> */
    #[ORM\OneToMany(targetEntity: JuridicoAtendimentoMensagem::class, mappedBy: 'ticket', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['criadoEm' => 'ASC'])]
    private Collection $mensagens;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->slaLimiteEm = $this->criadoEm->modify('+' . self::SLA_STANDARD_HORAS . ' hours');
        $this->mensagens = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getCliente(): ?JuridicoCliente { return $this->cliente; }
    public function setCliente(?JuridicoCliente $cliente): static { $this->cliente = $cliente; return $this; }
    public function getProcesso(): ?JuridicoProcesso { return $this->processo; }
    public function setProcesso(?JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getResponsavel(): ?User { return $this->responsavel; }
    public function setResponsavel(?User $responsavel): static { $this->responsavel = $responsavel; return $this; }
    public function getAssunto(): string { return $this->assunto; }
    public function setAssunto(string $assunto): static { $this->assunto = $assunto; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getCanal(): string { return $this->canal; }
    public function setCanal(string $canal): static { $this->canal = $canal; return $this; }
    public function getPrioridade(): string { return $this->prioridade; }
    public function setPrioridade(string $prioridade): static { $this->prioridade = $prioridade; return $this; }
    public function getSlaHoras(): int { return $this->slaHoras; }
    public function setSlaHoras(int $slaHoras): static { $this->slaHoras = $slaHoras; return $this; }
    public function getSlaLimiteEm(): \DateTimeImmutable { return $this->slaLimiteEm; }
    public function setSlaLimiteEm(\DateTimeImmutable $slaLimiteEm): static { $this->slaLimiteEm = $slaLimiteEm; return $this; }
    public function getPrimeiraRespostaEm(): ?\DateTimeImmutable { return $this->primeiraRespostaEm; }
    public function setPrimeiraRespostaEm(?\DateTimeImmutable $primeiraRespostaEm): static { $this->primeiraRespostaEm = $primeiraRespostaEm; return $this; }
    public function getResolvidoEm(): ?\DateTimeImmutable { return $this->resolvidoEm; }
    public function setResolvidoEm(?\DateTimeImmutable $resolvidoEm): static { $this->resolvidoEm = $resolvidoEm; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }

    /** @return Collection<int, JuridicoAtendimentoMensagem> */
    public function getMensagens(): Collection { return $this->mensagens; }

    public function addMensagem(JuridicoAtendimentoMensagem $mensagem): static
    {
        if (!$this->mensagens->contains($mensagem)) {
            $this->mensagens->add($mensagem);
            $mensagem->setTicket($this);
        }

        return $this;
    }

    public function touch(): static
    {
        $this->atualizadoEm = new \DateTimeImmutable();

        return $this;
    }

    public function isAberto(): bool
    {
        return $this->status !== self::STATUS_RESOLVIDO;
    }

    public function isSlaEstourado(): bool
    {
        if (!$this->isAberto() || $this->primeiraRespostaEm !== null) {
            return false;
        }

        return $this->slaLimiteEm < new \DateTimeImmutable();
    }

    public function aplicarSlaPorCliente(?JuridicoCliente $cliente): static
    {
        $horas = self::SLA_STANDARD_HORAS;
        $prioridade = self::PRIORIDADE_NORMAL;

        if ($cliente !== null && $cliente->getStatus() === JuridicoCliente::STATUS_PREMIUM) {
            $horas = self::SLA_PREMIUM_HORAS;
            $prioridade = self::PRIORIDADE_ALTA;
        }

        $this->slaHoras = $horas;
        $this->prioridade = $prioridade;
        $this->slaLimiteEm = $this->criadoEm->modify('+' . $horas . ' hours');

        return $this;
    }

    public function ultimaMensagem(): ?JuridicoAtendimentoMensagem
    {
        $ultima = null;
        foreach ($this->mensagens as $msg) {
            $ultima = $msg;
        }

        return $ultima;
    }
}
