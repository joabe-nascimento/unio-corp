<?php

namespace App\Entity;

use App\Repository\JuridicoPublicacaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoPublicacaoRepository::class)]
#[ORM\Table(name: 'juridico_publicacao')]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'IDX_JUR_PUB_EMPRESA_STATUS')]
#[ORM\Index(columns: ['empresa_id', 'prioridade'], name: 'IDX_JUR_PUB_EMPRESA_PRIOR')]
#[ORM\UniqueConstraint(name: 'UNIQ_JUR_PUB_DJEN', columns: ['empresa_id', 'djen_id'])]
class JuridicoPublicacao
{
    public const FONTE_DJEN = 'djen';
    public const FONTE_MANUAL = 'manual';

    public const STATUS_NAO_LIDA = 'nao_lida';
    public const STATUS_TRIAGEM = 'triagem';
    public const STATUS_VINCULADA = 'vinculada';
    public const STATUS_ARQUIVADA = 'arquivada';
    public const STATUS_CANCELADA = 'cancelada';

    public const PRIORIDADE_NORMAL = 'normal';
    public const PRIORIDADE_ALTA = 'alta';
    public const PRIORIDADE_CRITICA = 'critica';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoProcesso $processo = null;

    #[ORM\ManyToOne(targetEntity: JuridicoCliente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoCliente $cliente = null;

    #[ORM\Column(nullable: true)]
    private ?int $djenId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $hash = null;

    #[ORM\Column(length: 16)]
    private string $fonte = self::FONTE_DJEN;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $numeroProcesso = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $numeroProcessoNorm = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $tipoComunicacao = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $tipoDocumento = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $tribunal = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $orgao = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $classe = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataDisponibilizacao = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $texto = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_NAO_LIDA;

    #[ORM\Column(length: 16)]
    private string $prioridade = self::PRIORIDADE_NORMAL;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $iaClassificacao = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $iaResumo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $iaSugestaoAcao = null;

    #[ORM\Column(nullable: true)]
    private ?int $iaSugestaoPrazoDias = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $iaSugestaoTipoPrazo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motivoCancelamento = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lidaEm = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $triadaEm = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $triadaPor = null;

    #[ORM\Column]
    private bool $prazoCriado = false;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $atualizadoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getProcesso(): ?JuridicoProcesso { return $this->processo; }
    public function setProcesso(?JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getCliente(): ?JuridicoCliente { return $this->cliente; }
    public function setCliente(?JuridicoCliente $cliente): static { $this->cliente = $cliente; return $this; }
    public function getDjenId(): ?int { return $this->djenId; }
    public function setDjenId(?int $djenId): static { $this->djenId = $djenId; return $this; }
    public function getHash(): ?string { return $this->hash; }
    public function setHash(?string $hash): static { $this->hash = $hash; return $this; }
    public function getFonte(): string { return $this->fonte; }
    public function setFonte(string $fonte): static { $this->fonte = $fonte; return $this; }
    public function getNumeroProcesso(): ?string { return $this->numeroProcesso; }
    public function setNumeroProcesso(?string $numeroProcesso): static { $this->numeroProcesso = $numeroProcesso; return $this; }
    public function getNumeroProcessoNorm(): ?string { return $this->numeroProcessoNorm; }
    public function setNumeroProcessoNorm(?string $numeroProcessoNorm): static { $this->numeroProcessoNorm = $numeroProcessoNorm; return $this; }
    public function getTipoComunicacao(): ?string { return $this->tipoComunicacao; }
    public function setTipoComunicacao(?string $tipoComunicacao): static { $this->tipoComunicacao = $tipoComunicacao; return $this; }
    public function getTipoDocumento(): ?string { return $this->tipoDocumento; }
    public function setTipoDocumento(?string $tipoDocumento): static { $this->tipoDocumento = $tipoDocumento; return $this; }
    public function getTribunal(): ?string { return $this->tribunal; }
    public function setTribunal(?string $tribunal): static { $this->tribunal = $tribunal; return $this; }
    public function getOrgao(): ?string { return $this->orgao; }
    public function setOrgao(?string $orgao): static { $this->orgao = $orgao; return $this; }
    public function getClasse(): ?string { return $this->classe; }
    public function setClasse(?string $classe): static { $this->classe = $classe; return $this; }
    public function getDataDisponibilizacao(): ?\DateTimeImmutable { return $this->dataDisponibilizacao; }
    public function setDataDisponibilizacao(?\DateTimeImmutable $dataDisponibilizacao): static { $this->dataDisponibilizacao = $dataDisponibilizacao; return $this; }
    public function getTexto(): ?string { return $this->texto; }
    public function setTexto(?string $texto): static { $this->texto = $texto; return $this; }
    public function getLink(): ?string { return $this->link; }
    public function setLink(?string $link): static { $this->link = $link; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getPrioridade(): string { return $this->prioridade; }
    public function setPrioridade(string $prioridade): static { $this->prioridade = $prioridade; return $this; }
    public function getIaClassificacao(): ?string { return $this->iaClassificacao; }
    public function setIaClassificacao(?string $iaClassificacao): static { $this->iaClassificacao = $iaClassificacao; return $this; }
    public function getIaResumo(): ?string { return $this->iaResumo; }
    public function setIaResumo(?string $iaResumo): static { $this->iaResumo = $iaResumo; return $this; }
    public function getIaSugestaoAcao(): ?string { return $this->iaSugestaoAcao; }
    public function setIaSugestaoAcao(?string $iaSugestaoAcao): static { $this->iaSugestaoAcao = $iaSugestaoAcao; return $this; }
    public function getIaSugestaoPrazoDias(): ?int { return $this->iaSugestaoPrazoDias; }
    public function setIaSugestaoPrazoDias(?int $iaSugestaoPrazoDias): static { $this->iaSugestaoPrazoDias = $iaSugestaoPrazoDias; return $this; }
    public function getIaSugestaoTipoPrazo(): ?string { return $this->iaSugestaoTipoPrazo; }
    public function setIaSugestaoTipoPrazo(?string $iaSugestaoTipoPrazo): static { $this->iaSugestaoTipoPrazo = $iaSugestaoTipoPrazo; return $this; }
    public function getMotivoCancelamento(): ?string { return $this->motivoCancelamento; }
    public function setMotivoCancelamento(?string $motivoCancelamento): static { $this->motivoCancelamento = $motivoCancelamento; return $this; }
    public function getLidaEm(): ?\DateTimeImmutable { return $this->lidaEm; }
    public function setLidaEm(?\DateTimeImmutable $lidaEm): static { $this->lidaEm = $lidaEm; return $this; }
    public function getTriadaEm(): ?\DateTimeImmutable { return $this->triadaEm; }
    public function setTriadaEm(?\DateTimeImmutable $triadaEm): static { $this->triadaEm = $triadaEm; return $this; }
    public function getTriadaPor(): ?User { return $this->triadaPor; }
    public function setTriadaPor(?User $triadaPor): static { $this->triadaPor = $triadaPor; return $this; }
    public function isPrazoCriado(): bool { return $this->prazoCriado; }
    public function setPrazoCriado(bool $prazoCriado): static { $this->prazoCriado = $prazoCriado; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }

    public function isLida(): bool
    {
        return $this->lidaEm !== null;
    }

    public function isCancelada(): bool
    {
        return $this->status === self::STATUS_CANCELADA || $this->motivoCancelamento !== null;
    }

    public function tituloCurto(): string
    {
        $partes = array_filter([
            $this->tipoComunicacao,
            $this->tipoDocumento,
            $this->tribunal,
        ]);

        return $partes !== [] ? implode(' · ', $partes) : 'Publicação';
    }
}
