<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiNotificacao;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiNotificacao> */
class TiNotificacaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiNotificacao::class);
    }

    /** @return list<TiNotificacao> */
    public function findUnreadByUser(Empresa $empresa, User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.empresa = :empresa')
            ->andWhere('n.user = :user')
            ->andWhere('n.lida = false')
            ->setParameter('empresa', $empresa)
            ->setParameter('user', $user)
            ->orderBy('n.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnread(Empresa $empresa, User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.empresa = :empresa')
            ->andWhere('n.user = :user')
            ->andWhere('n.lida = false')
            ->setParameter('empresa', $empresa)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
