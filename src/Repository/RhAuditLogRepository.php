<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhAuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhAuditLog>
 */
class RhAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhAuditLog::class);
    }

    /** @return list<RhAuditLog> */
    public function findForEmpresa(Empresa $empresa, ?string $modulo = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('a.criadoEm', 'DESC')
            ->setMaxResults($limit);

        if ($modulo !== null && $modulo !== '') {
            $qb->andWhere('a.modulo = :modulo')->setParameter('modulo', $modulo);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
