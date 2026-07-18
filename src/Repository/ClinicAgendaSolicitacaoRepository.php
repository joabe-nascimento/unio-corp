<?php

namespace App\Repository;

use App\Entity\ClinicAgendaSolicitacao;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicAgendaSolicitacao> */
class ClinicAgendaSolicitacaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicAgendaSolicitacao::class);
    }

    /** @return list<ClinicAgendaSolicitacao> */
    public function findPendingByEmpresa(Empresa $empresa, int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.empresa = :empresa')
            ->andWhere('s.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', ClinicAgendaSolicitacao::STATUS_PENDENTE)
            ->orderBy('s.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countPendingByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.empresa = :empresa')
            ->andWhere('s.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', ClinicAgendaSolicitacao::STATUS_PENDENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
