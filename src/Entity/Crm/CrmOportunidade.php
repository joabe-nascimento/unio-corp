<?php

namespace App\Entity\Crm;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\Crm\CrmOportunidadeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrmOportunidadeRepository::class)]
#[ORM\Table(name: 'crm_oportunidade')]
#[ORM\Index(columns: ['empresa_id', 'estagio'], name: 'idx_crm_oportunidade_empresa_estagio')]
class CrmOportunidade
{
    public const STAGE_LEAD = 'lead';
    public const STAGE_QUALIFICACAO = 'qualificacao';
    public const STAGE_PROPOSTA = 'proposta';
    public const STAGE_NEGOCIACAO = 'negociacao';
    public const STAGE_GANHO = 'ganho';
    public const STAGE_PERDIDO = 'perdido';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: CrmConta::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CrmConta $conta = null;

    #[ORM\ManyToOne(targetEntity: CrmLead::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CrmLead $lead = null;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(length: 24)]
    private string $estagio = self::STAGE_LEAD;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $valor = null;

    #[ORM\Column(type: 'smallint')]
    private int $probabilidade = 20;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $fechaPrevista = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notas = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

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

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getConta(): ?CrmConta { return $this->conta; }
    public function setConta(?CrmConta $conta): static { $this->conta = $conta; return $this; }
    public function getLead(): ?CrmLead { return $this->lead; }
    public function setLead(?CrmLead $lead): static { $this->lead = $lead; return $this; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getEstagio(): string { return $this->estagio; }
    public function setEstagio(string $estagio): static { $this->estagio = $estagio; $this->touch(); return $this; }
    public function getValor(): ?string { return $this->valor; }
    public function setValor(?string $valor): static { $this->valor = $valor; return $this; }
    public function getProbabilidade(): int { return $this->probabilidade; }
    public function setProbabilidade(int $probabilidade): static { $this->probabilidade = max(0, min(100, $probabilidade)); return $this; }
    public function getFechaPrevista(): ?\DateTimeImmutable { return $this->fechaPrevista; }
    public function setFechaPrevista(?\DateTimeImmutable $fechaPrevista): static { $this->fechaPrevista = $fechaPrevista; return $this; }
    public function getNotas(): ?string { return $this->notas; }
    public function setNotas(?string $notas): static { $this->notas = $notas; return $this; }
    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $owner): static { $this->owner = $owner; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }

    /** @return list<string> */
    public static function stagesOpen(): array
    {
        return [self::STAGE_LEAD, self::STAGE_QUALIFICACAO, self::STAGE_PROPOSTA, self::STAGE_NEGOCIACAO];
    }

    /** @return list<string> */
    public static function stagesAll(): array
    {
        return [...self::stagesOpen(), self::STAGE_GANHO, self::STAGE_PERDIDO];
    }

    /** @return array<string, array{label: string, tone: string}> */
    public static function stageMeta(): array
    {
        return [
            self::STAGE_LEAD => ['label' => 'Lead', 'tone' => 'sky'],
            self::STAGE_QUALIFICACAO => ['label' => 'Qualificação', 'tone' => 'amber'],
            self::STAGE_PROPOSTA => ['label' => 'Proposta', 'tone' => 'lavender'],
            self::STAGE_NEGOCIACAO => ['label' => 'Negociação', 'tone' => 'sage'],
            self::STAGE_GANHO => ['label' => 'Ganho', 'tone' => 'success'],
            self::STAGE_PERDIDO => ['label' => 'Perdido', 'tone' => 'rose'],
        ];
    }
}
