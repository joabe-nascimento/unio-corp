<?php

namespace App\Repository;

use App\Entity\Departamento;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Departamento> */
class DepartamentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Departamento::class);
    }

    /** @return list<Departamento> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('d.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Departamento> */
    public function findByEmpresaWithFilters(Empresa $empresa, ?string $q = null, ?string $area = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.lider', 'l')
            ->andWhere('d.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('d.nome', 'ASC');

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('d.nome LIKE :q OR l.nome LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        if ($area !== null && trim($area) !== '') {
            $qb->andWhere('d.area = :area')->setParameter('area', trim($area));
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWithLider(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.empresa = :empresa')
            ->andWhere('d.lider IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<string> */
    public function findDistinctAreas(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('DISTINCT d.area AS area')
            ->andWhere('d.empresa = :empresa')
            ->andWhere('d.area IS NOT NULL')
            ->andWhere('d.area != :empty')
            ->setParameter('empresa', $empresa)
            ->setParameter('empty', '')
            ->orderBy('d.area', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['area'] ?? ''),
            $rows
        )));
    }
}
