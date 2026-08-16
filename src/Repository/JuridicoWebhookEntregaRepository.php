<?php

namespace App\Repository;

use App\Entity\JuridicoWebhookEntrega;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoWebhookEntrega> */
class JuridicoWebhookEntregaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoWebhookEntrega::class);
    }
}
