<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhEsocialLote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhEsocialLote>
 */
class RhEsocialLoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhEsocialLote::class);
    }

    /** @return list<RhEsocialLote> */
    public function findForEmpresa(Empresa $empresa, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('l.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
