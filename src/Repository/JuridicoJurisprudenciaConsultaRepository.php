<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoJurisprudenciaConsulta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoJurisprudenciaConsulta> */
class JuridicoJurisprudenciaConsultaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoJurisprudenciaConsulta::class);
    }

    /** @return list<JuridicoJurisprudenciaConsulta> */
    public function findRecentForEmpresa(Empresa $empresa, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countSince(Empresa $empresa, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.criadoEm >= :since')
            ->setParameter('empresa', $empresa)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
