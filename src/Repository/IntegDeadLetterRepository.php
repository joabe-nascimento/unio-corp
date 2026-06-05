<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegDeadLetter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegDeadLetter> */
class IntegDeadLetterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegDeadLetter::class);
    }

    /** @return list<IntegDeadLetter> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('dl')
            ->andWhere('dl.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('dl.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findForEmpresaById(Empresa $empresa, int $id): ?IntegDeadLetter
    {
        return $this->findOneBy(['empresa' => $empresa, 'id' => $id]);
    }

    public function countForEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('dl')
            ->select('COUNT(dl.id)')
            ->andWhere('dl.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
