<?php

namespace App\Repository;

use App\Entity\JuridicoPublicacao;
use App\Entity\JuridicoPublicacaoEvento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoPublicacaoEvento> */
class JuridicoPublicacaoEventoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoPublicacaoEvento::class);
    }

    /** @return list<JuridicoPublicacaoEvento> */
    public function findForPublicacao(JuridicoPublicacao $publicacao, int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.publicacao = :p')
            ->setParameter('p', $publicacao)
            ->orderBy('e.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
