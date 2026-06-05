<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegPlaybookRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegPlaybookRun> */
class IntegPlaybookRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegPlaybookRun::class);
    }

    /** @return list<IntegPlaybookRun> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('r.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
