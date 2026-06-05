<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegMapeamento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegMapeamento> */
class IntegMapeamentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegMapeamento::class);
    }

    /** @return list<IntegMapeamento> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.conector', 'c')
            ->addSelect('c')
            ->andWhere('m.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('m.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?IntegMapeamento
    {
        return $this->findOneBy(['empresa' => $empresa, 'id' => $id]);
    }
}
