<?php

namespace App\Repository;

use App\Entity\ApiToken;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ApiToken> */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    public function findActiveByHash(string $hash): ?ApiToken
    {
        return $this->findOneBy(['tokenHash' => $hash, 'ativo' => true]);
    }

    /** @return list<ApiToken> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('t.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countAtivosByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.empresa = :empresa')
            ->andWhere('t.ativo = true')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
