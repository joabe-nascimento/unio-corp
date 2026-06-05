<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiPlaybook;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiPlaybook> */
class TiPlaybookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiPlaybook::class);
    }

    /** @return list<TiPlaybook> */
    public function findActiveByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.ativo = true')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.titulo', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
