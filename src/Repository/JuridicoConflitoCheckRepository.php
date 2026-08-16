<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoConflitoCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoConflitoCheck> */
class JuridicoConflitoCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoConflitoCheck::class);
    }

    /** @return list<JuridicoConflitoCheck> */
    public function findForEmpresa(Empresa $empresa, int $limit = 80): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :e')
            ->setParameter('e', $empresa)
            ->orderBy('c.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
