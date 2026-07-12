<?php

namespace App\Repository;

use App\Entity\ClinicCheckin;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicCheckin> */
class ClinicCheckinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicCheckin::class);
    }

    /** @return list<ClinicCheckin> */
    public function findTodayByEmpresa(Empresa $empresa): array
    {
        $start = new \DateTimeImmutable('today');
        $end = $start->modify('+1 day');

        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.criadoEm >= :start')
            ->andWhere('c.criadoEm < :end')
            ->setParameter('empresa', $empresa)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('c.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
