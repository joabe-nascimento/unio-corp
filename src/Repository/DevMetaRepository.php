<?php

namespace App\Repository;

use App\Entity\DevMeta;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DevMeta> */
class DevMetaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevMeta::class);
    }

    /** @return list<DevMeta> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('m.dataAlvo', 'ASC')
            ->addOrderBy('m.atualizadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
