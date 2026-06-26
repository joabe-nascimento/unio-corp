<?php

namespace App\Repository;

use App\Entity\PosOperatorioProtocolo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PosOperatorioProtocolo> */
class PosOperatorioProtocoloRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PosOperatorioProtocolo::class);
    }
}
