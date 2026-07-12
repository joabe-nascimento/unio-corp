<?php

namespace App\Repository;

use App\Entity\ClinicVerificacaoLog;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicVerificacaoLog> */
class ClinicVerificacaoLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicVerificacaoLog::class);
    }

    /** @return list<ClinicVerificacaoLog> */
    public function findRecentByEmpresa(Empresa $empresa, int $limit = 200): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('l.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
