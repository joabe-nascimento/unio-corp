<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhFolhaCompetencia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RhFolhaCompetencia> */
class RhFolhaCompetenciaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhFolhaCompetencia::class);
    }

    /** @return list<RhFolhaCompetencia> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.referencia', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByReferencia(Empresa $empresa, string $referencia): ?RhFolhaCompetencia
    {
        return $this->findOneBy(['empresa' => $empresa, 'referencia' => $referencia]);
    }
}
