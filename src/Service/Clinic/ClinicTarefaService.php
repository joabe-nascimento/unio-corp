<?php

namespace App\Service\Clinic;

use App\Entity\ClinicTarefa;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\ClinicTarefaRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicTarefaService
{
    public function __construct(
        private ClinicTarefaRepository $tarefas,
        private EntityManagerInterface $em,
    ) {}

    public function create(Empresa $empresa, string $titulo, ?User $criadoPor = null, ?\DateTimeImmutable $vencimento = null, ?string $descricao = null): ClinicTarefa
    {
        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new \InvalidArgumentException('Informe o título da tarefa.');
        }

        $tarefa = (new ClinicTarefa())
            ->setEmpresa($empresa)
            ->setTitulo($titulo)
            ->setDescricao($descricao !== null && trim($descricao) !== '' ? trim($descricao) : null)
            ->setVencimento($vencimento)
            ->setCriadoPor($criadoPor)
            ->setStatus(ClinicTarefa::STATUS_PENDENTE);

        $this->em->persist($tarefa);
        $this->em->flush();

        return $tarefa;
    }

    /** @return list<array<string, mixed>> */
    public function listPendingRows(Empresa $empresa, int $limit = 8): array
    {
        return array_map(fn (ClinicTarefa $t): array => $this->mapRow($t), $this->tarefas->findPendingByEmpresa($empresa, $limit));
    }

    public function countPending(Empresa $empresa): int
    {
        return $this->tarefas->countPendingByEmpresa($empresa);
    }

    public function complete(ClinicTarefa $tarefa): void
    {
        $tarefa->setStatus(ClinicTarefa::STATUS_CONCLUIDA);
        $tarefa->setConcluidaEm(new \DateTimeImmutable());
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    private function mapRow(ClinicTarefa $t): array
    {
        return [
            'id' => $t->getId(),
            'titulo' => $t->getTitulo(),
            'descricao' => $t->getDescricao(),
            'vencimento' => $t->getVencimento()?->format('d/m/Y'),
            'vencimento_hoje' => $t->getVencimento() !== null
                && $t->getVencimento()->format('Y-m-d') === (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'responsavel' => $t->getResponsavel()?->getNome(),
            'criado_em' => $t->getCriadoEm()->format('d/m H:i'),
        ];
    }
}
