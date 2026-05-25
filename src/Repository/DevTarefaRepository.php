<?php

namespace App\Repository;

use App\Entity\DevTarefa;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DevTarefa> */
class DevTarefaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevTarefa::class);
    }

    /** @return list<DevTarefa> */
    public function findByEmpresa(Empresa $empresa, ?int $projetoId = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('t.ordem', 'ASC')
            ->addOrderBy('t.atualizadoEm', 'DESC');

        if ($projetoId) {
            $qb->andWhere('t.projeto = :projeto')->setParameter('projeto', $projetoId);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return array<string, list<DevTarefa>> */
    public function groupByStatus(Empresa $empresa, ?int $projetoId = null): array
    {
        $grouped = [];
        foreach (array_keys(DevTarefa::KANBAN_COLUMNS) as $status) {
            $grouped[$status] = [];
        }
        foreach ($this->findByEmpresa($empresa, $projetoId) as $tarefa) {
            $grouped[$tarefa->getStatus()][] = $tarefa;
        }

        return $grouped;
    }
}
