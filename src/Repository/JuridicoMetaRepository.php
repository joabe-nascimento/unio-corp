<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoMeta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoMeta> */
class JuridicoMetaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoMeta::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoMeta
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoMeta> */
    public function findForEmpresaPeriodo(Empresa $empresa, string $periodo): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.responsavel', 'r')
            ->addSelect('r')
            ->andWhere('m.empresa = :empresa')
            ->andWhere('m.periodo = :periodo')
            ->setParameter('empresa', $empresa)
            ->setParameter('periodo', $periodo)
            ->orderBy('m.criadoEm', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
