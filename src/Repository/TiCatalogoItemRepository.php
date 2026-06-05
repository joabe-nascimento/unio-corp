<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiCatalogoItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiCatalogoItem> */
class TiCatalogoItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiCatalogoItem::class);
    }
}
