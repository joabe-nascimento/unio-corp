<?php

namespace App\Entity;

use App\Repository\TiIntegracaoLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiIntegracaoLogRepository::class)]
#[ORM\Table(name: 'ti_integracao_log')]
#[ORM\Index(name: 'IDX_TI_INT_LOG_EMPRESA', columns: ['empresa_id', 'registrado_em'])]
class TiIntegracaoLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: TiIntegracao::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TiIntegracao $integracao = null;

    #[ORM\Column(length: 64)]
    private string $conector;

    #[ORM\Column(length: 16)]
    private string $nivel = 'info';

    #[ORM\Column(type: 'text')]
    private string $mensagem;

    #[ORM\Column]
    private \DateTimeImmutable $registradoEm;

    public function __construct()
    {
        $this->registradoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'time' => $this->registradoEm->format('H:i'),
            'connector' => $this->conector,
            'level' => $this->nivel,
            'message' => $this->mensagem,
        ];
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

    public function getIntegracao(): ?TiIntegracao
    {
        return $this->integracao;
    }

    public function setIntegracao(?TiIntegracao $integracao): static
    {
        $this->integracao = $integracao;

        return $this;
    }

    public function getConector(): string
    {
        return $this->conector;
    }

    public function setConector(string $conector): static
    {
        $this->conector = $conector;

        return $this;
    }

    public function getNivel(): string
    {
        return $this->nivel;
    }

    public function setNivel(string $nivel): static
    {
        $this->nivel = $nivel;

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

    public function setRegistradoEm(\DateTimeImmutable $registradoEm): static
    {
        $this->registradoEm = $registradoEm;

        return $this;
    }
}
