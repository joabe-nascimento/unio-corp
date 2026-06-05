<?php

namespace App\Repository;

use App\Entity\ChatConversation;
use App\Entity\ChatParticipant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ChatParticipant> */
class ChatParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatParticipant::class);
    }

    public function findOneForUser(ChatConversation $conversation, User $user): ?ChatParticipant
    {
        return $this->findOneBy(['conversation' => $conversation, 'user' => $user]);
    }
}
