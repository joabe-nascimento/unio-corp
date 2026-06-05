<?php

namespace App\Repository;

use App\Entity\ChatConversation;
use App\Entity\ChatMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ChatMessage> */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    public function findLatest(ChatConversation $conversation, int $limit = 1): ?ChatMessage
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<ChatMessage> */
    public function findSince(ChatConversation $conversation, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conv')
            ->andWhere('m.createdAt > :since')
            ->setParameter('conv', $conversation)
            ->setParameter('since', $since)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{0: list<ChatMessage>, 1: bool}
     */
    public function findPage(
        ChatConversation $conversation,
        int $limit = 50,
        ?\DateTimeImmutable $before = null,
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit + 1);

        if ($before) {
            $qb->andWhere('m.createdAt < :before')->setParameter('before', $before);
        }

        $rows = $qb->getQuery()->getResult();
        $hasMore = \count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        return [array_reverse($rows), $hasMore];
    }

    /** @return list<ChatMessage> */
    public function findGalleryItems(ChatConversation $conversation): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conv')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.messageType != :system')
            ->setParameter('conv', $conversation)
            ->setParameter('system', ChatMessage::TYPE_SYSTEM)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
