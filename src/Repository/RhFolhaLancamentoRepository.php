<?php

namespace App\Repository;

use App\Entity\RhFolhaCompetencia;
use App\Entity\RhFolhaLancamento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RhFolhaLancamento> */
class RhFolhaLancamentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhFolhaLancamento::class);
    }

    /** @return list<RhFolhaLancamento> */
    public function findByCompetencia(RhFolhaCompetencia $competencia): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.competencia = :competencia')
            ->setParameter('competencia', $competencia)
            ->orderBy('l.funcionario', 'ASC')
            ->addOrderBy('l.tipo', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
