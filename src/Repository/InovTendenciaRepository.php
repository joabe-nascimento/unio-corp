<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\InovTendencia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InovTendencia> */
class InovTendenciaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InovTendencia::class);
    }

    /** @return list<InovTendencia> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('t.ordem', 'ASC')
            ->addOrderBy('t.valor', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?InovTendencia
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }
}
