<?php

namespace App\Repository;

use App\Entity\DevProjeto;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DevProjeto> */
class DevProjetoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevProjeto::class);
    }

    /** @return list<DevProjeto> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.atualizadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countEmAndamento(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status = :s')
            ->setParameter('empresa', $empresa)
            ->setParameter('s', DevProjeto::STATUS_EM_ANDAMENTO)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
