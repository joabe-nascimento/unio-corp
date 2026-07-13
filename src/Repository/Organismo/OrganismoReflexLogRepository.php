<?php

namespace App\Repository\Organismo;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoReflexLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrganismoReflexLog> */
class OrganismoReflexLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganismoReflexLog::class);
    }

    /** @return list<OrganismoReflexLog> */
    public function findRecent(Empresa $empresa, int $limit = 8): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('r.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countTodayByCode(Empresa $empresa, string $code): int
    {
        $today = new \DateTimeImmutable('today');

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.empresa = :empresa')
            ->andWhere('r.reflexCode = :code')
            ->andWhere('r.criadoEm >= :today')
            ->setParameter('empresa', $empresa)
            ->setParameter('code', $code)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
