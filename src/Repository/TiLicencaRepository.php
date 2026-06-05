<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiLicenca;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiLicenca> */
class TiLicencaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiLicenca::class);
    }

    /** @return list<TiLicenca> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('l.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?TiLicenca
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }
}
