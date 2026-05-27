<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhComunicado;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhComunicado>
 */
class RhComunicadoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhComunicado::class);
    }

    /** @return list<RhComunicado> */
    public function findAtivosForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.ativo = true')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.publicadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countAtivosByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.ativo = true')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
