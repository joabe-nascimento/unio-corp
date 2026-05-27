<?php

namespace App\Repository;

use App\Entity\Departamento;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Departamento> */
class DepartamentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Departamento::class);
    }

    /** @return list<Departamento> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('d.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
