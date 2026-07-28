<?php

namespace App\Repository;

use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EmpresaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Empresa::class);
    }

    public function findBySlug(string $slug): ?Empresa
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.slug = :slug')
            ->andWhere('e.ativo = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.slug = :slug')
            ->setParameter('slug', $slug);
        if ($excludeId !== null) {
            $qb->andWhere('e.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findByCodigoGrupo(string $codigo): ?Empresa
    {
        return $this->findOneBy(['codigoGrupo' => $codigo]);
    }
}
