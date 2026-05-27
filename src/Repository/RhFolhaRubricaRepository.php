<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhFolhaRubrica;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhFolhaRubrica>
 */
class RhFolhaRubricaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhFolhaRubrica::class);
    }

    /** @return list<RhFolhaRubrica> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('r.codigo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
