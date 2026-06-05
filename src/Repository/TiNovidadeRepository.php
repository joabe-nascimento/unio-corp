<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiNovidade;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiNovidade> */
class TiNovidadeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiNovidade::class);
    }

    /** @return list<TiNovidade> */
    public function findByEmpresa(Empresa $empresa, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('n.publicadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?TiNovidade
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }
}
