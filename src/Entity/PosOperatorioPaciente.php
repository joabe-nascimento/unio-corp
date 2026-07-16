<?php

namespace App\Entity;

use App\Repository\PosOperatorioPacienteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paciente em acompanhamento pós-operatório.
 */
#[ORM\Entity(repositoryClass: PosOperatorioPacienteRepository::class)]
#[ORM\Table(name: 'pos_operatorio_paciente')]
#[ORM\Index(name: 'IDX_POSOP_PAC_EMPRESA_STATUS', columns: ['empresa_id', 'status'])]
#[ORM\UniqueConstraint(name: 'UNIQ_POSOP_PAC_CODIGO', columns: ['empresa_id', 'codigo'])]
class PosOperatorioPaciente
{
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_ALERTA = 'alerta';
    public const STATUS_ENCERRADO = 'encerrado';
    public const STATUS_PENDENTE = 'pendente';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: PosOperatorioProtocolo::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PosOperatorioProtocolo $protocolo = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $medicoResponsavel = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $portalUser = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $portalInviteToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $portalInviteExpiresAt = null;

    #[ORM\Column(length: 16)]
    private string $codigo = '';

    #[ORM\Column(length: 160)]
    private string $nome = '';

    #[ORM\Column(length: 11, nullable: true)]
    private ?string $cpf = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataNascimento = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $procedimento = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataCirurgia = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ATIVO;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefoneContato = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $emailContato = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $contatoEmergencia = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefoneEmergencia = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacoes = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $consentimentoLgpdEm = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fotoPath = null;

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $carteirinhaPlano = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $carteirinhaVerificacao = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $carteirinhaEmitidaEm = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $carteirinhaValidaAte = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $comprovanteVerificacao = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $comprovanteEmitidaEm = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $comprovanteValidoAte = null;

    #[ORM\Column(length: 11, nullable: true)]
    private ?string $titularCpf = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSandbox = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $consentimentoCarteirinhaEm = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $comprovanteHash = null;

    #[ORM\ManyToOne(targetEntity: ClinicUnidade::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicUnidade $unidade = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $sexo = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $rg = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $cns = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $cep = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $logradouro = null;

    #[ORM\Column(name: 'numero_endereco', length: 20, nullable: true)]
    private ?string $numeroEndereco = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $complemento = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $bairro = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $cidade = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $uf = null;

    #[ORM\ManyToOne(targetEntity: ClinicConvenio::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicConvenio $convenio = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $numeroCarteirinhaConvenio = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validadeCarteirinhaConvenio = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $origemClinica = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $indicadoPor = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $titularNome = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $parentescoTitular = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $anamnese = null;

    #[ORM\ManyToOne(targetEntity: ClinicProfissional::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicProfissional $profissional = null;

    #[ORM\ManyToOne(targetEntity: ClinicProcedimento::class)]
    #[ORM\JoinColumn(name: 'procedimento_id', nullable: true, onDelete: 'SET NULL')]
    private ?ClinicProcedimento $procedimentoCatalogo = null;

    #[ORM\ManyToOne(targetEntity: ClinicPacote::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicPacote $pacote = null;

    /** @var Collection<int, PosOperatorioQuestionarioResposta> */
    #[ORM\OneToMany(mappedBy: 'paciente', targetEntity: PosOperatorioQuestionarioResposta::class, cascade: ['persist'])]
    #[ORM\OrderBy(['respondidoEm' => 'DESC'])]
    private Collection $questionarios;

    /** @var Collection<int, PosOperatorioAlerta> */
    #[ORM\OneToMany(mappedBy: 'paciente', targetEntity: PosOperatorioAlerta::class, cascade: ['persist'])]
    #[ORM\OrderBy(['criadoEm' => 'DESC'])]
    private Collection $alertas;

    /** @var Collection<int, PosOperatorioEvento> */
    #[ORM\OneToMany(mappedBy: 'paciente', targetEntity: PosOperatorioEvento::class, cascade: ['persist'])]
    #[ORM\OrderBy(['criadoEm' => 'DESC'])]
    private Collection $eventos;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->questionarios = new ArrayCollection();
        $this->alertas = new ArrayCollection();
        $this->eventos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getProtocolo(): ?PosOperatorioProtocolo
    {
        return $this->protocolo;
    }

    public function setProtocolo(?PosOperatorioProtocolo $protocolo): static
    {
        $this->protocolo = $protocolo;

        return $this;
    }

    public function getMedicoResponsavel(): ?User
    {
        return $this->medicoResponsavel;
    }

    public function setMedicoResponsavel(?User $medicoResponsavel): static
    {
        $this->medicoResponsavel = $medicoResponsavel;

        return $this;
    }

    public function getPortalUser(): ?User
    {
        return $this->portalUser;
    }

    public function setPortalUser(?User $portalUser): static
    {
        $this->portalUser = $portalUser;

        return $this;
    }

    public function getPortalInviteToken(): ?string
    {
        return $this->portalInviteToken;
    }

    public function setPortalInviteToken(?string $portalInviteToken): static
    {
        $this->portalInviteToken = $portalInviteToken;

        return $this;
    }

    public function getPortalInviteExpiresAt(): ?\DateTimeImmutable
    {
        return $this->portalInviteExpiresAt;
    }

    public function setPortalInviteExpiresAt(?\DateTimeImmutable $portalInviteExpiresAt): static
    {
        $this->portalInviteExpiresAt = $portalInviteExpiresAt;

        return $this;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = $codigo;

        return $this;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

        return $this;
    }

    public function getCpf(): ?string
    {
        return $this->cpf;
    }

    public function setCpf(?string $cpf): static
    {
        $this->cpf = $cpf;

        return $this;
    }

    public function getDataNascimento(): ?\DateTimeImmutable
    {
        return $this->dataNascimento;
    }

    public function setDataNascimento(?\DateTimeImmutable $dataNascimento): static
    {
        $this->dataNascimento = $dataNascimento;

        return $this;
    }

    public function getProcedimento(): ?string
    {
        return $this->procedimento;
    }

    public function setProcedimento(?string $procedimento): static
    {
        $this->procedimento = $procedimento;

        return $this;
    }

    public function getDataCirurgia(): ?\DateTimeImmutable
    {
        return $this->dataCirurgia;
    }

    public function setDataCirurgia(?\DateTimeImmutable $dataCirurgia): static
    {
        $this->dataCirurgia = $dataCirurgia;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTelefoneContato(): ?string
    {
        return $this->telefoneContato;
    }

    public function setTelefoneContato(?string $telefoneContato): static
    {
        $this->telefoneContato = $telefoneContato;

        return $this;
    }

    public function getEmailContato(): ?string
    {
        return $this->emailContato;
    }

    public function setEmailContato(?string $emailContato): static
    {
        $this->emailContato = $emailContato;

        return $this;
    }

    public function getContatoEmergencia(): ?string
    {
        return $this->contatoEmergencia;
    }

    public function setContatoEmergencia(?string $contatoEmergencia): static
    {
        $this->contatoEmergencia = $contatoEmergencia;

        return $this;
    }

    public function getTelefoneEmergencia(): ?string
    {
        return $this->telefoneEmergencia;
    }

    public function setTelefoneEmergencia(?string $telefoneEmergencia): static
    {
        $this->telefoneEmergencia = $telefoneEmergencia;

        return $this;
    }

    public function getObservacoes(): ?string
    {
        return $this->observacoes;
    }

    public function setObservacoes(?string $observacoes): static
    {
        $this->observacoes = $observacoes;

        return $this;
    }

    public function getConsentimentoLgpdEm(): ?\DateTimeImmutable
    {
        return $this->consentimentoLgpdEm;
    }

    public function setConsentimentoLgpdEm(?\DateTimeImmutable $consentimentoLgpdEm): static
    {
        $this->consentimentoLgpdEm = $consentimentoLgpdEm;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getFotoPath(): ?string
    {
        return $this->fotoPath;
    }

    public function setFotoPath(?string $fotoPath): static
    {
        $this->fotoPath = $fotoPath;

        return $this;
    }

    public function getCarteirinhaPlano(): ?string
    {
        return $this->carteirinhaPlano;
    }

    public function setCarteirinhaPlano(?string $carteirinhaPlano): static
    {
        $this->carteirinhaPlano = $carteirinhaPlano;

        return $this;
    }

    public function getCarteirinhaVerificacao(): ?string
    {
        return $this->carteirinhaVerificacao;
    }

    public function setCarteirinhaVerificacao(?string $carteirinhaVerificacao): static
    {
        $this->carteirinhaVerificacao = $carteirinhaVerificacao;

        return $this;
    }

    public function getCarteirinhaEmitidaEm(): ?\DateTimeImmutable
    {
        return $this->carteirinhaEmitidaEm;
    }

    public function setCarteirinhaEmitidaEm(?\DateTimeImmutable $carteirinhaEmitidaEm): static
    {
        $this->carteirinhaEmitidaEm = $carteirinhaEmitidaEm;

        return $this;
    }

    public function getCarteirinhaValidaAte(): ?\DateTimeImmutable
    {
        return $this->carteirinhaValidaAte;
    }

    public function setCarteirinhaValidaAte(?\DateTimeImmutable $carteirinhaValidaAte): static
    {
        $this->carteirinhaValidaAte = $carteirinhaValidaAte;

        return $this;
    }

    public function hasCarteirinhaAtiva(): bool
    {
        if ($this->carteirinhaVerificacao === null || $this->carteirinhaEmitidaEm === null) {
            return false;
        }

        if ($this->carteirinhaValidaAte === null) {
            return true;
        }

        return $this->carteirinhaValidaAte >= new \DateTimeImmutable('today');
    }

    public function getComprovanteVerificacao(): ?string
    {
        return $this->comprovanteVerificacao;
    }

    public function setComprovanteVerificacao(?string $comprovanteVerificacao): static
    {
        $this->comprovanteVerificacao = $comprovanteVerificacao !== null ? strtoupper(trim($comprovanteVerificacao)) : null;

        return $this;
    }

    public function getComprovanteEmitidaEm(): ?\DateTimeImmutable
    {
        return $this->comprovanteEmitidaEm;
    }

    public function setComprovanteEmitidaEm(?\DateTimeImmutable $comprovanteEmitidaEm): static
    {
        $this->comprovanteEmitidaEm = $comprovanteEmitidaEm;

        return $this;
    }

    public function getComprovanteValidoAte(): ?\DateTimeImmutable
    {
        return $this->comprovanteValidoAte;
    }

    public function setComprovanteValidoAte(?\DateTimeImmutable $comprovanteValidoAte): static
    {
        $this->comprovanteValidoAte = $comprovanteValidoAte;

        return $this;
    }

    public function hasComprovanteAtivo(): bool
    {
        if ($this->comprovanteVerificacao === null || $this->comprovanteEmitidaEm === null) {
            return false;
        }

        if ($this->comprovanteValidoAte === null) {
            return true;
        }

        return $this->comprovanteValidoAte >= new \DateTimeImmutable('today');
    }

    public function getTitularCpf(): ?string
    {
        return $this->titularCpf;
    }

    public function setTitularCpf(?string $titularCpf): static
    {
        $this->titularCpf = $titularCpf;

        return $this;
    }

    public function getCpfTitularEfetivo(): ?string
    {
        return $this->titularCpf ?? $this->cpf;
    }

    public function isSandbox(): bool
    {
        return $this->isSandbox;
    }

    public function setIsSandbox(bool $isSandbox): static
    {
        $this->isSandbox = $isSandbox;

        return $this;
    }

    public function getConsentimentoCarteirinhaEm(): ?\DateTimeImmutable
    {
        return $this->consentimentoCarteirinhaEm;
    }

    public function setConsentimentoCarteirinhaEm(?\DateTimeImmutable $consentimentoCarteirinhaEm): static
    {
        $this->consentimentoCarteirinhaEm = $consentimentoCarteirinhaEm;

        return $this;
    }

    public function getComprovanteHash(): ?string
    {
        return $this->comprovanteHash;
    }

    public function setComprovanteHash(?string $comprovanteHash): static
    {
        $this->comprovanteHash = $comprovanteHash;

        return $this;
    }

    public function getUnidade(): ?ClinicUnidade
    {
        return $this->unidade;
    }

    public function setUnidade(?ClinicUnidade $unidade): static
    {
        $this->unidade = $unidade;

        return $this;
    }

    public function getSexo(): ?string
    {
        return $this->sexo;
    }

    public function setSexo(?string $sexo): static
    {
        $this->sexo = $sexo;

        return $this;
    }

    public function getRg(): ?string
    {
        return $this->rg;
    }

    public function setRg(?string $rg): static
    {
        $this->rg = $rg;

        return $this;
    }

    public function getCns(): ?string
    {
        return $this->cns;
    }

    public function setCns(?string $cns): static
    {
        $this->cns = $cns;

        return $this;
    }

    public function getCep(): ?string
    {
        return $this->cep;
    }

    public function setCep(?string $cep): static
    {
        $this->cep = $cep;

        return $this;
    }

    public function getLogradouro(): ?string
    {
        return $this->logradouro;
    }

    public function setLogradouro(?string $logradouro): static
    {
        $this->logradouro = $logradouro;

        return $this;
    }

    public function getNumeroEndereco(): ?string
    {
        return $this->numeroEndereco;
    }

    public function setNumeroEndereco(?string $numeroEndereco): static
    {
        $this->numeroEndereco = $numeroEndereco;

        return $this;
    }

    public function getComplemento(): ?string
    {
        return $this->complemento;
    }

    public function setComplemento(?string $complemento): static
    {
        $this->complemento = $complemento;

        return $this;
    }

    public function getBairro(): ?string
    {
        return $this->bairro;
    }

    public function setBairro(?string $bairro): static
    {
        $this->bairro = $bairro;

        return $this;
    }

    public function getCidade(): ?string
    {
        return $this->cidade;
    }

    public function setCidade(?string $cidade): static
    {
        $this->cidade = $cidade;

        return $this;
    }

    public function getUf(): ?string
    {
        return $this->uf;
    }

    public function setUf(?string $uf): static
    {
        $this->uf = $uf;

        return $this;
    }

    public function getConvenio(): ?ClinicConvenio
    {
        return $this->convenio;
    }

    public function setConvenio(?ClinicConvenio $convenio): static
    {
        $this->convenio = $convenio;

        return $this;
    }

    public function getNumeroCarteirinhaConvenio(): ?string
    {
        return $this->numeroCarteirinhaConvenio;
    }

    public function setNumeroCarteirinhaConvenio(?string $numeroCarteirinhaConvenio): static
    {
        $this->numeroCarteirinhaConvenio = $numeroCarteirinhaConvenio;

        return $this;
    }

    public function getValidadeCarteirinhaConvenio(): ?\DateTimeImmutable
    {
        return $this->validadeCarteirinhaConvenio;
    }

    public function setValidadeCarteirinhaConvenio(?\DateTimeImmutable $validadeCarteirinhaConvenio): static
    {
        $this->validadeCarteirinhaConvenio = $validadeCarteirinhaConvenio;

        return $this;
    }

    public function getOrigemClinica(): ?string
    {
        return $this->origemClinica;
    }

    public function setOrigemClinica(?string $origemClinica): static
    {
        $this->origemClinica = $origemClinica;

        return $this;
    }

    public function getIndicadoPor(): ?string
    {
        return $this->indicadoPor;
    }

    public function setIndicadoPor(?string $indicadoPor): static
    {
        $this->indicadoPor = $indicadoPor;

        return $this;
    }

    public function getTitularNome(): ?string
    {
        return $this->titularNome;
    }

    public function setTitularNome(?string $titularNome): static
    {
        $this->titularNome = $titularNome;

        return $this;
    }

    public function getParentescoTitular(): ?string
    {
        return $this->parentescoTitular;
    }

    public function setParentescoTitular(?string $parentescoTitular): static
    {
        $this->parentescoTitular = $parentescoTitular;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getAnamnese(): ?array
    {
        return $this->anamnese;
    }

    /** @param array<string, mixed>|null $anamnese */
    public function setAnamnese(?array $anamnese): static
    {
        $this->anamnese = $anamnese;

        return $this;
    }

    public function getProfissional(): ?ClinicProfissional
    {
        return $this->profissional;
    }

    public function setProfissional(?ClinicProfissional $profissional): static
    {
        $this->profissional = $profissional;

        return $this;
    }

    public function getProcedimentoCatalogo(): ?ClinicProcedimento
    {
        return $this->procedimentoCatalogo;
    }

    public function setProcedimentoCatalogo(?ClinicProcedimento $procedimentoCatalogo): static
    {
        $this->procedimentoCatalogo = $procedimentoCatalogo;

        return $this;
    }

    public function getPacote(): ?ClinicPacote
    {
        return $this->pacote;
    }

    public function setPacote(?ClinicPacote $pacote): static
    {
        $this->pacote = $pacote;

        return $this;
    }

    public function getStatusClinicoLabel(): ?string
    {
        $dia = $this->getDiaPosOperatorio();
        if ($dia === null) {
            return null;
        }

        if ($this->status === self::STATUS_ALERTA) {
            return sprintf('Dia %d pós-op · alerta em acompanhamento', $dia);
        }

        return sprintf('Dia %d pós-op', $dia);
    }

    /** @return Collection<int, PosOperatorioQuestionarioResposta> */
    public function getQuestionarios(): Collection
    {
        return $this->questionarios;
    }

    /** @return Collection<int, PosOperatorioAlerta> */
    public function getAlertas(): Collection
    {
        return $this->alertas;
    }

    /** @return Collection<int, PosOperatorioEvento> */
    public function getEventos(): Collection
    {
        return $this->eventos;
    }

    /**
     * Dias relativos à cirurgia: negativo = pré-op (D−n), zero = dia da cirurgia, positivo = pós (D+n).
     */
    public function getDiaRelativoCirurgia(?\DateTimeImmutable $ref = null): ?int
    {
        if (!$this->dataCirurgia) {
            return null;
        }
        $ref ??= new \DateTimeImmutable('today');
        $surgery = $this->dataCirurgia->setTime(0, 0);
        $day = $ref->setTime(0, 0);
        $diff = $surgery->diff($day);

        return $diff->invert === 1 ? -((int) $diff->days) : (int) $diff->days;
    }

    public function isPreOperatorio(?\DateTimeImmutable $ref = null): bool
    {
        $rel = $this->getDiaRelativoCirurgia($ref);

        return $rel !== null && $rel < 0;
    }

    /** Dia da cirurgia (D0): handoff pré → pós. */
    public function isDiaCirurgia(?\DateTimeImmutable $ref = null): bool
    {
        return $this->getDiaRelativoCirurgia($ref) === 0;
    }

    /** Dias desde a cirurgia (só pós-op); null se ainda não operou ou sem data. */
    public function getDiaPosOperatorio(?\DateTimeImmutable $ref = null): ?int
    {
        $rel = $this->getDiaRelativoCirurgia($ref);
        if ($rel === null || $rel < 0) {
            return null;
        }

        return $rel;
    }

    public static function formatDiaRelativoLabel(int $dia): string
    {
        if ($dia < 0) {
            return 'D−'.abs($dia);
        }
        if ($dia === 0) {
            return 'D0';
        }

        return 'D+'.$dia;
    }

    public function getUltimaResposta(): ?PosOperatorioQuestionarioResposta
    {
        $first = $this->questionarios->first();

        return $first instanceof PosOperatorioQuestionarioResposta ? $first : null;
    }
}
