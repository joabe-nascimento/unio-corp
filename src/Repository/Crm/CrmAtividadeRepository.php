<?php

namespace App\Repository\Crm;

use App\Entity\Crm\CrmAtividade;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CrmAtividade> */
class CrmAtividadeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrmAtividade::class);
    }

    /** @return list<CrmAtividade> */
    public function findPendentes(Empresa $empresa, int $limit = 30): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.concluida = false')
            ->setParameter('empresa', $empresa)
            ->orderBy('a.venceEm', 'ASC')
            ->addOrderBy('a.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countPendentes(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.concluida = false')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
