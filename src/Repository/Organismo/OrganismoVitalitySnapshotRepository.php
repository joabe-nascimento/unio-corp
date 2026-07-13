<?php

namespace App\Repository\Organismo;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoVitalitySnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrganismoVitalitySnapshot> */
class OrganismoVitalitySnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganismoVitalitySnapshot::class);
    }

    public function findLatest(Empresa $empresa): ?OrganismoVitalitySnapshot
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('v.criadoEm', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPrevious(Empresa $empresa, OrganismoVitalitySnapshot $current): ?OrganismoVitalitySnapshot
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('v.id != :id')
            ->setParameter('empresa', $empresa)
            ->setParameter('id', $current->getId())
            ->orderBy('v.criadoEm', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
