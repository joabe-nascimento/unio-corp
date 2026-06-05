<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiManutencao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiManutencao> */
class TiManutencaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiManutencao::class);
    }

    /** @return list<TiManutencao> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('m.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresaAndStatus(Empresa $empresa, string $status): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.empresa = :empresa')
            ->andWhere('m.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?TiManutencao
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }
}
