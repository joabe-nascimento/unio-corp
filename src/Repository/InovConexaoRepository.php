<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\InovConexao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InovConexao> */
class InovConexaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InovConexao::class);
    }

    /** @return list<InovConexao> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.sinergia', 'DESC')
            ->addOrderBy('c.atualizadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?InovConexao
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }
}
