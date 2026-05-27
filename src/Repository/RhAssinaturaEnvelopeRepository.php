<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhAssinaturaEnvelope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhAssinaturaEnvelope>
 */
class RhAssinaturaEnvelopeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhAssinaturaEnvelope::class);
    }

    /** @return list<RhAssinaturaEnvelope> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('e.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
