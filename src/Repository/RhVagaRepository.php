<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhVaga;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhVaga>
 */
class RhVagaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhVaga::class);
    }

    /** @return list<RhVaga> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('v.criadoEm', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('v.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAbertasByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('v.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhVaga::STATUS_ABERTA)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
