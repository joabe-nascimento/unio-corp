<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\InovImpactEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InovImpactEntry> */
class InovImpactEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InovImpactEntry::class);
    }

    /** @return list<InovImpactEntry> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('e.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?InovImpactEntry
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }
}
