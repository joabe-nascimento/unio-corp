<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiIntegracao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiIntegracao> */
class TiIntegracaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiIntegracao::class);
    }

    /** @return list<TiIntegracao> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('i.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?TiIntegracao
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }
}
