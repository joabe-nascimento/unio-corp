<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhEmailEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhEmailEvent>
 */
class RhEmailEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhEmailEvent::class);
    }

    public function countPendentesByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.empresa = :empresa')
            ->andWhere('e.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhEmailEvent::STATUS_PENDENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<RhEmailEvent> */
    public function findPending(?Empresa $empresa, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->setParameter('status', RhEmailEvent::STATUS_PENDENTE)
            ->orderBy('e.criadoEm', 'ASC')
            ->setMaxResults(max(1, $limit));

        if ($empresa !== null) {
            $qb->andWhere('e.empresa = :empresa')->setParameter('empresa', $empresa);
        }

        return $qb->getQuery()->getResult();
    }
}
