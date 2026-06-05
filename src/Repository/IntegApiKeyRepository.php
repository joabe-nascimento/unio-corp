<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegApiKey> */
class IntegApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegApiKey::class);
    }

    /** @return list<IntegApiKey> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('k.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?IntegApiKey
    {
        return $this->findOneBy(['empresa' => $empresa, 'id' => $id]);
    }
}
