<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\PessoasCargo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PessoasCargo> */
class PessoasCargoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PessoasCargo::class);
    }

    /** @return list<PessoasCargo> */
    public function findByEmpresa(Empresa $empresa, ?string $q = null, ?bool $ativo = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.titulo', 'ASC');

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('c.titulo LIKE :q OR c.area LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        if ($ativo !== null) {
            $qb->andWhere('c.ativo = :ativo')->setParameter('ativo', $ativo);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.ativo = true')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsTitulo(Empresa $empresa, string $titulo, ?int $excludeId = null): bool
    {
        $normalized = mb_strtolower(trim($titulo));
        if ($normalized === '') {
            return false;
        }

        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('LOWER(c.titulo) = :titulo')
            ->setParameter('empresa', $empresa)
            ->setParameter('titulo', $normalized);

        if ($excludeId !== null) {
            $qb->andWhere('c.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return list<string> */
    public function findDistinctAreas(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.area AS area')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.area IS NOT NULL')
            ->andWhere('c.area != :empty')
            ->setParameter('empresa', $empresa)
            ->setParameter('empty', '')
            ->orderBy('c.area', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['area'] ?? ''),
            $rows
        )));
    }
}
