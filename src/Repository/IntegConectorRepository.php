<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegConector;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegConector> */
class IntegConectorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegConector::class);
    }

    /** @return list<IntegConector> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?IntegConector
    {
        return $this->findOneBy(['empresa' => $empresa, 'id' => $id]);
    }

    public function countForEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByCatalogoId(Empresa $empresa, string $catalogoId): ?IntegConector
    {
        return $this->findOneBy(['empresa' => $empresa, 'catalogoId' => $catalogoId]);
    }
}
