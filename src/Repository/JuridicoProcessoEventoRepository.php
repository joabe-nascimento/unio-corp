<?php

namespace App\Repository;

use App\Entity\JuridicoProcesso;
use App\Entity\JuridicoProcessoEvento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoProcessoEvento> */
class JuridicoProcessoEventoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoProcessoEvento::class);
    }

    /** @return list<JuridicoProcessoEvento> */
    public function findForProcesso(JuridicoProcesso $processo, int $limit = 80, bool $soPortal = false): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.processo = :p')
            ->setParameter('p', $processo)
            ->orderBy('e.ocorreuEm', 'DESC')
            ->setMaxResults($limit);

        if ($soPortal) {
            $qb->andWhere('e.visivelPortal = true');
        }

        return $qb->getQuery()->getResult();
    }
}
