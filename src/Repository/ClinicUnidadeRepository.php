<?php

namespace App\Repository;

use App\Entity\ClinicUnidade;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicUnidade> */
class ClinicUnidadeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicUnidade::class);
    }

    /** @return list<ClinicUnidade> */
    public function findAtivasByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.empresa = :empresa')
            ->andWhere('u.ativo = true')
            ->setParameter('empresa', $empresa)
            ->orderBy('u.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
