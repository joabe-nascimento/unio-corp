<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhOffboardingProcess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhOffboardingProcess>
 */
class RhOffboardingProcessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhOffboardingProcess::class);
    }

    /** @return list<RhOffboardingProcess> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countOpenByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                RhOffboardingProcess::STATUS_RASCUNHO,
                RhOffboardingProcess::STATUS_EM_ANDAMENTO,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
