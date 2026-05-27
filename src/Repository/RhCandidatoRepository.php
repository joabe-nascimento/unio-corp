<?php

namespace App\Repository;

use App\Entity\RhCandidato;
use App\Entity\RhVaga;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhCandidato>
 */
class RhCandidatoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhCandidato::class);
    }

    /** @return list<RhCandidato> */
    public function findByVaga(RhVaga $vaga): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.vaga = :vaga')
            ->setParameter('vaga', $vaga)
            ->orderBy('c.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByVaga(RhVaga $vaga): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.vaga = :vaga')
            ->setParameter('vaga', $vaga)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
