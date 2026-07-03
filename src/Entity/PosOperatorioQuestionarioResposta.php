<?php

namespace App\Entity;

use App\Repository\PosOperatorioQuestionarioRespostaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PosOperatorioQuestionarioRespostaRepository::class)]
#[ORM\Table(name: 'pos_operatorio_questionario_resposta')]
#[ORM\UniqueConstraint(name: 'UNIQ_POSOP_QR_PAC_DATA', columns: ['paciente_id', 'data_referencia'])]
class PosOperatorioQuestionarioResposta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class, inversedBy: 'questionarios')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PosOperatorioPaciente $paciente;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dataReferencia;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $respostas = [];

    #[ORM\Column(type: 'smallint')]
    private int $scoreRisco = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $alertaGerado = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $respondidoEm;

    public function __construct()
    {
        $this->respondidoEm = new \DateTimeImmutable();
        $this->dataReferencia = new \DateTimeImmutable('today');
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

    public function getDataReferencia(): \DateTimeImmutable
    {
        return $this->dataReferencia;
    }

    public function setDataReferencia(\DateTimeImmutable $dataReferencia): static
    {
        $this->dataReferencia = $dataReferencia;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getRespostas(): array
    {
        return $this->respostas;
    }

    /** @param array<string, mixed> $respostas */
    public function setRespostas(array $respostas): static
    {
        $this->respostas = $respostas;

        return $this;
    }

    public function getScoreRisco(): int
    {
        return $this->scoreRisco;
    }

    public function setScoreRisco(int $scoreRisco): static
    {
        $this->scoreRisco = $scoreRisco;

        return $this;
    }

    public function isAlertaGerado(): bool
    {
        return $this->alertaGerado;
    }

    public function setAlertaGerado(bool $alertaGerado): static
    {
        $this->alertaGerado = $alertaGerado;

        return $this;
    }

    public function getRespondidoEm(): \DateTimeImmutable
    {
        return $this->respondidoEm;
    }
}
