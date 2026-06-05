<?php

namespace App\Repository;

use App\Entity\TiChamado;
use App\Entity\TiChamadoAnexo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiChamadoAnexo> */
class TiChamadoAnexoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiChamadoAnexo::class);
    }

    /** @return list<TiChamadoAnexo> */
    public function findByChamado(TiChamado $chamado): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.chamado = :chamado')
            ->setParameter('chamado', $chamado)
            ->orderBy('a.criadoEm', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
