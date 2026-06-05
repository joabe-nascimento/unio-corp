<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiIntegracaoLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiIntegracaoLog> */
class TiIntegracaoLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiIntegracaoLog::class);
    }

    /** @return list<TiIntegracaoLog> */
    public function findRecentByEmpresa(Empresa $empresa, int $limit = 20): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('l.registradoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
