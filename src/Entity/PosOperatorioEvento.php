<?php

namespace App\Entity;

use App\Repository\PosOperatorioEventoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PosOperatorioEventoRepository::class)]
#[ORM\Table(name: 'pos_operatorio_evento')]
#[ORM\Index(name: 'IDX_POSOP_EVT_PACIENTE', columns: ['paciente_id', 'criado_em'])]
class PosOperatorioEvento
{
    public const TIPO_CADASTRO = 'cadastro';
    public const TIPO_QUESTIONARIO = 'questionario';
    public const TIPO_ALERTA = 'alerta';
    public const TIPO_CHAT = 'chat';
    public const TIPO_VITORIA = 'vitoria';
    public const TIPO_ACESSO_FICHA = 'acesso_ficha';
    public const TIPO_CONSENTIMENTO = 'consentimento';
    public const TIPO_LEMBRETE = 'lembrete';
    public const TIPO_EVOLUCAO = 'evolucao';
    public const TIPO_RETORNO = 'retorno';

    /** @var list<string> */
    public const TIPOS_VISIVEIS_PACIENTE = [
        self::TIPO_CHAT,
        self::TIPO_EVOLUCAO,
        self::TIPO_RETORNO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class, inversedBy: 'eventos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PosOperatorioPaciente $paciente;

    #[ORM\Column(length: 32)]
    private string $tipo = self::TIPO_CADASTRO;

    #[ORM\Column(type: 'text')]
    private string $descricao = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $autor = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPaciente(): PosOperatorioPaciente
    {
        return $this->paciente;
    }

    public function setPaciente(PosOperatorioPaciente $paciente): static
    {
        $this->paciente = $paciente;

        return $this;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getDescricao(): string
    {
        return $this->descricao;
    }

    public function setDescricao(string $descricao): static
    {
        $this->descricao = $descricao;

        return $this;
    }

    public function getAutor(): ?User
    {
        return $this->autor;
    }

    public function setAutor(?User $autor): static
    {
        $this->autor = $autor;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
