<?php

namespace App\Entity;

use App\Repository\IntegShadowRunRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegShadowRunRepository::class)]
#[ORM\Table(name: 'integ_shadow_run')]
class IntegShadowRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: IntegMapeamento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?IntegMapeamento $mapeamento = null;

    #[ORM\Column(length: 120)]
    private string $mapeamentoNome;

    #[ORM\Column(length: 80)]
    private string $campoOrigem;

    #[ORM\Column(length: 80)]
    private string $campoDestinoAtual;

    #[ORM\Column(length: 80)]
    private string $campoDestinoProposto;

    #[ORM\Column]
    private int $periodoDias = 7;

    #[ORM\Column]
    private int $totalEventos = 0;

    #[ORM\Column]
    private int $sucesso = 0;

    #[ORM\Column]
    private int $falhas = 0;

    #[ORM\Column]
    private int $duplicatas = 0;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $amostras = [];

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $taxa = $this->totalEventos > 0
            ? round(($this->sucesso / $this->totalEventos) * 100, 1)
            : 100.0;

        return [
            'db_id' => $this->id,
            'mapeamento_id' => $this->mapeamento?->getId(),
            'mapeamento_nome' => $this->mapeamentoNome,
            'campo_origem' => $this->campoOrigem,
            'campo_destino_atual' => $this->campoDestinoAtual,
            'campo_destino_proposto' => $this->campoDestinoProposto,
            'periodo_dias' => $this->periodoDias,
            'total_eventos' => $this->totalEventos,
            'sucesso' => $this->sucesso,
            'falhas' => $this->falhas,
            'duplicatas' => $this->duplicatas,
            'taxa_sucesso' => $taxa,
            'amostras' => $this->amostras,
            'criado_em' => $this->criadoEm->format('d/m H:i:s'),
        ];
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

    public function getMapeamento(): ?IntegMapeamento
    {
        return $this->mapeamento;
    }

    public function setMapeamento(?IntegMapeamento $mapeamento): static
    {
        $this->mapeamento = $mapeamento;

        return $this;
    }

    public function getMapeamentoNome(): string
    {
        return $this->mapeamentoNome;
    }

    public function setMapeamentoNome(string $mapeamentoNome): static
    {
        $this->mapeamentoNome = $mapeamentoNome;

        return $this;
    }

    public function getCampoOrigem(): string
    {
        return $this->campoOrigem;
    }

    public function setCampoOrigem(string $campoOrigem): static
    {
        $this->campoOrigem = $campoOrigem;

        return $this;
    }

    public function getCampoDestinoAtual(): string
    {
        return $this->campoDestinoAtual;
    }

    public function setCampoDestinoAtual(string $campoDestinoAtual): static
    {
        $this->campoDestinoAtual = $campoDestinoAtual;

        return $this;
    }

    public function getCampoDestinoProposto(): string
    {
        return $this->campoDestinoProposto;
    }

    public function setCampoDestinoProposto(string $campoDestinoProposto): static
    {
        $this->campoDestinoProposto = $campoDestinoProposto;

        return $this;
    }

    public function getPeriodoDias(): int
    {
        return $this->periodoDias;
    }

    public function setPeriodoDias(int $periodoDias): static
    {
        $this->periodoDias = $periodoDias;

        return $this;
    }

    public function getTotalEventos(): int
    {
        return $this->totalEventos;
    }

    public function setTotalEventos(int $totalEventos): static
    {
        $this->totalEventos = $totalEventos;

        return $this;
    }

    public function getSucesso(): int
    {
        return $this->sucesso;
    }

    public function setSucesso(int $sucesso): static
    {
        $this->sucesso = $sucesso;

        return $this;
    }

    public function getFalhas(): int
    {
        return $this->falhas;
    }

    public function setFalhas(int $falhas): static
    {
        $this->falhas = $falhas;

        return $this;
    }

    public function getDuplicatas(): int
    {
        return $this->duplicatas;
    }

    public function setDuplicatas(int $duplicatas): static
    {
        $this->duplicatas = $duplicatas;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getAmostras(): array
    {
        return $this->amostras;
    }

    /** @param list<array<string, mixed>> $amostras */
    public function setAmostras(array $amostras): static
    {
        $this->amostras = $amostras;

        return $this;
    }
}
