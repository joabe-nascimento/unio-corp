<?php

namespace App\Repository;

use App\Entity\RhCandidato;
use App\Entity\RhCandidatoAnexo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RhCandidatoAnexo> */
class RhCandidatoAnexoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhCandidatoAnexo::class);
    }

    /** @return list<RhCandidatoAnexo> */
    public function findByCandidato(RhCandidato $candidato): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.candidato = :candidato')
            ->setParameter('candidato', $candidato)
            ->orderBy('a.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
