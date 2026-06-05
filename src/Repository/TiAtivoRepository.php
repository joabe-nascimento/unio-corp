<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiAtivo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiAtivo> */
class TiAtivoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiAtivo::class);
    }

    /** @return list<TiAtivo> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('a.codigo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByEmpresaAndStatus(Empresa $empresa, string $status): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSemResponsavel(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.responsavel IS NULL OR a.responsavel = :vazio')
            ->setParameter('empresa', $empresa)
            ->setParameter('vazio', '')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?TiAtivo
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    public function findByCodigoForEmpresa(Empresa $empresa, string $codigo): ?TiAtivo
    {
        return $this->findOneBy(['empresa' => $empresa, 'codigo' => $codigo]);
    }
}
