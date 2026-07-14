<?php

namespace App\Repository\Organismo;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoCareAttestation;
use App\Entity\Organismo\OrganismoCareContract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrganismoCareAttestation> */
class OrganismoCareAttestationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganismoCareAttestation::class);
    }

    public function findLatest(OrganismoCareContract $contract): ?OrganismoCareAttestation
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.contract = :contract')
            ->setParameter('contract', $contract)
            ->orderBy('a.criadoEm', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByMarco(OrganismoCareContract $contract, string $marcoKey): ?OrganismoCareAttestation
    {
        return $this->findOneBy(['contract' => $contract, 'marcoKey' => $marcoKey]);
    }

    /** @return list<OrganismoCareAttestation> */
    public function findRecentByEmpresa(Empresa $empresa, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.contract', 'c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('a.criadoEm', 'DESC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->getQuery()
            ->getResult();
    }
}
