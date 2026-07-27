<?php

namespace App\Repository;

use App\Entity\JuridicoProcesso;
use App\Entity\JuridicoProcessoParte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoProcessoParte> */
class JuridicoProcessoParteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoProcessoParte::class);
    }

    public function findOneByProcesso(JuridicoProcesso $processo, int $id): ?JuridicoProcessoParte
    {
        return $this->findOneBy(['id' => $id, 'processo' => $processo]);
    }

    /** @return list<JuridicoProcessoParte> */
    public function findForProcesso(JuridicoProcesso $processo): array
    {
        return $this->createQueryBuilder('pt')
            ->andWhere('pt.processo = :processo')
            ->setParameter('processo', $processo)
            ->orderBy('pt.polo', 'ASC')
            ->addOrderBy('pt.criadoEm', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
