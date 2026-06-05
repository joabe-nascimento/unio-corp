<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegSlo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegSlo> */
class IntegSloRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegSlo::class);
    }

    /** @return list<IntegSlo> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('s.flowKey', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countForEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findForEmpresaByFlowKey(Empresa $empresa, string $flowKey): ?IntegSlo
    {
        return $this->findOneBy(['empresa' => $empresa, 'flowKey' => $flowKey]);
    }
}
