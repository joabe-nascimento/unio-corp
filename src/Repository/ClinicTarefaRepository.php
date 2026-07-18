<?php

namespace App\Repository;

use App\Entity\ClinicTarefa;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicTarefa> */
class ClinicTarefaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicTarefa::class);
    }

    /** @return list<ClinicTarefa> */
    public function findPendingByEmpresa(Empresa $empresa, int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :empresa')
            ->andWhere('t.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', ClinicTarefa::STATUS_PENDENTE)
            ->orderBy('t.vencimento', 'ASC')
            ->addOrderBy('t.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countPendingByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.empresa = :empresa')
            ->andWhere('t.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', ClinicTarefa::STATUS_PENDENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
