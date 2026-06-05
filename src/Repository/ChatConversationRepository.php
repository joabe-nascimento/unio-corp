<?php

namespace App\Repository;

use App\Entity\ChatConversation;
use App\Entity\Empresa;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ChatConversation> */
class ChatConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatConversation::class);
    }

    /** @return list<ChatConversation> */
    public function findForUser(User $user, Empresa $empresa): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->andWhere('p.user = :user')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('user', $user)
            ->setParameter('empresa', $empresa)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findDirectBetween(Empresa $empresa, User $a, User $b): ?ChatConversation
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p1')
            ->innerJoin('c.participants', 'p2')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.type = :type')
            ->andWhere('p1.user = :a')
            ->andWhere('p2.user = :b')
            ->setParameter('empresa', $empresa)
            ->setParameter('type', ChatConversation::TYPE_DIRECT)
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
