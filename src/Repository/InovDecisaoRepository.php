<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\InovDecisao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InovDecisao> */
class InovDecisaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InovDecisao::class);
    }

    /** @return list<InovDecisao> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('d.decididoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?InovDecisao
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }
}
