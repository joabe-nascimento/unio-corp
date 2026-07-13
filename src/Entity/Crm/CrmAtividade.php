<?php

namespace App\Entity\Crm;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\Crm\CrmAtividadeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrmAtividadeRepository::class)]
#[ORM\Table(name: 'crm_atividade')]
#[ORM\Index(columns: ['empresa_id', 'concluida'], name: 'idx_crm_atividade_empresa_done')]
class CrmAtividade
{
    public const TIPO_LIGACAO = 'ligacao';
    public const TIPO_EMAIL = 'email';
    public const TIPO_REUNIAO = 'reuniao';
    public const TIPO_TAREFA = 'tarefa';
    public const TIPO_NOTA = 'nota';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: CrmLead::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CrmLead $lead = null;

    #[ORM\ManyToOne(targetEntity: CrmConta::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CrmConta $conta = null;

    #[ORM\ManyToOne(targetEntity: CrmOportunidade::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CrmOportunidade $oportunidade = null;

    #[ORM\Column(length: 24)]
    private string $tipo = self::TIPO_TAREFA;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $venceEm = null;

    #[ORM\Column]
    private bool $concluida = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsavel = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getLead(): ?CrmLead { return $this->lead; }
    public function setLead(?CrmLead $lead): static { $this->lead = $lead; return $this; }
    public function getConta(): ?CrmConta { return $this->conta; }
    public function setConta(?CrmConta $conta): static { $this->conta = $conta; return $this; }
    public function getOportunidade(): ?CrmOportunidade { return $this->oportunidade; }
    public function setOportunidade(?CrmOportunidade $oportunidade): static { $this->oportunidade = $oportunidade; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }
    public function getVenceEm(): ?\DateTimeImmutable { return $this->venceEm; }
    public function setVenceEm(?\DateTimeImmutable $venceEm): static { $this->venceEm = $venceEm; return $this; }
    public function isConcluida(): bool { return $this->concluida; }
    public function setConcluida(bool $concluida): static { $this->concluida = $concluida; return $this; }
    public function getResponsavel(): ?User { return $this->responsavel; }
    public function setResponsavel(?User $responsavel): static { $this->responsavel = $responsavel; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    /** @return list<string> */
    public static function tipoList(): array
    {
        return [self::TIPO_LIGACAO, self::TIPO_EMAIL, self::TIPO_REUNIAO, self::TIPO_TAREFA, self::TIPO_NOTA];
    }

    /** @return array<string, string> */
    public static function tipoLabels(): array
    {
        return [
            self::TIPO_LIGACAO => 'Ligação',
            self::TIPO_EMAIL => 'E-mail',
            self::TIPO_REUNIAO => 'Reunião',
            self::TIPO_TAREFA => 'Tarefa',
            self::TIPO_NOTA => 'Nota',
        ];
    }

    public static function tipoLabel(string $tipo): string
    {
        return self::tipoLabels()[$tipo] ?? $tipo;
    }
}
