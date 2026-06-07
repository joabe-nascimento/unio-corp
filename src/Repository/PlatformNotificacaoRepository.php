<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\PlatformNotificacao;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PlatformNotificacao> */
class PlatformNotificacaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformNotificacao::class);
    }

    /** @return list<PlatformNotificacao> */
    public function findForUser(Empresa $empresa, User $user, ?bool $unreadOnly = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.empresa = :empresa')
            ->andWhere('n.user = :user')
            ->setParameter('empresa', $empresa)
            ->setParameter('user', $user)
            ->orderBy('n.criadoEm', 'DESC')
            ->setMaxResults($limit);

        if ($unreadOnly === true) {
            $qb->andWhere('n.lida = false');
        }

        return $qb->getQuery()->getResult();
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

    public function markAllRead(Empresa $empresa, User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.lida', ':lida')
            ->andWhere('n.empresa = :empresa')
            ->andWhere('n.user = :user')
            ->andWhere('n.lida = false')
            ->setParameter('lida', true)
            ->setParameter('empresa', $empresa)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
