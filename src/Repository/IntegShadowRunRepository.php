<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegShadowRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegShadowRun> */
class IntegShadowRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegShadowRun::class);
    }

    /** @return list<IntegShadowRun> */
    public function findRecentForEmpresa(Empresa $empresa, int $limit = 5): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('s.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
