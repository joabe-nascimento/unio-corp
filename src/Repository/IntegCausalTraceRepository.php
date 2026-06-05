<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegCausalTrace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegCausalTrace> */
class IntegCausalTraceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegCausalTrace::class);
    }

    public function countForEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<IntegCausalTrace> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('t.ultimoEventoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByFlowKey(Empresa $empresa, string $flowKey): ?IntegCausalTrace
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :empresa')
            ->andWhere('t.flowKey = :key')
            ->setParameter('empresa', $empresa)
            ->setParameter('key', $flowKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
