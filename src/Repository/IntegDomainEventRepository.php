<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegDomainEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegDomainEvent> */
class IntegDomainEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegDomainEvent::class);
    }

    /** @return list<IntegDomainEvent> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('e.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<IntegDomainEvent> */
    public function findPending(Empresa $empresa): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.empresa = :empresa')
            ->andWhere('e.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', IntegDomainEvent::STATUS_PENDENTE)
            ->orderBy('e.criadoEm', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
