<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegSchemaDrift;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegSchemaDrift> */
class IntegSchemaDriftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegSchemaDrift::class);
    }

    public function countOpenForEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.empresa = :empresa')
            ->andWhere('d.resolvido = false')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<IntegSchemaDrift> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('d.resolvido', 'ASC')
            ->addOrderBy('d.detectadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
