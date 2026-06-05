<?php

namespace App\Repository;

use App\Entity\ChatCallSignal;
use App\Entity\ChatConversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ChatCallSignal> */
class ChatCallSignalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatCallSignal::class);
    }

    /** @return list<ChatCallSignal> */
    public function findSince(ChatConversation $conversation, User $forUser, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.conversation = :conv')
            ->andWhere('s.createdAt > :since')
            ->andWhere('s.fromUser != :user')
            ->andWhere('s.toUser IS NULL OR s.toUser = :user')
            ->setParameter('conv', $conversation)
            ->setParameter('since', $since)
            ->setParameter('user', $forUser)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
