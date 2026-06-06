<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhTalentoPool;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RhTalentoPool> */
class RhTalentoPoolRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhTalentoPool::class);
    }

    /** @return list<RhTalentoPool> */
    public function findForEmpresa(Empresa $empresa, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('t.atualizadoEm', 'DESC');

        $q = $q !== null ? trim($q) : '';
        if ($q !== '') {
            $qb->andWhere(
                'LOWER(t.nome) LIKE :q OR LOWER(t.email) LIKE :q OR LOWER(t.observacoes) LIKE :q',
            )->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByEmail(Empresa $empresa, string $email): ?RhTalentoPool
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :empresa')
            ->andWhere('LOWER(t.email) = :email')
            ->setParameter('empresa', $empresa)
            ->setParameter('email', mb_strtolower(trim($email)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
