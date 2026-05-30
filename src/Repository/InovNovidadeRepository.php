<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\InovNovidade;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InovNovidade> */
class InovNovidadeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InovNovidade::class);
    }

    /** @return list<InovNovidade> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('n.publicadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?InovNovidade
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }
}
