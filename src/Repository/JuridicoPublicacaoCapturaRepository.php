<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoPublicacaoCaptura;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoPublicacaoCaptura> */
class JuridicoPublicacaoCapturaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoPublicacaoCaptura::class);
    }

    /** @return list<JuridicoPublicacaoCaptura> */
    public function findAtivasByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.ativo = true')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.ufOab', 'ASC')
            ->addOrderBy('c.numeroOab', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<JuridicoPublicacaoCaptura> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.ativo', 'DESC')
            ->addOrderBy('c.ufOab', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<JuridicoPublicacaoCaptura> */
    public function findTodasAtivas(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.ativo = true')
            ->getQuery()
            ->getResult();
    }
}
