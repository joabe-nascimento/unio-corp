<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiKbArtigo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiKbArtigo> */
class TiKbArtigoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiKbArtigo::class);
    }

    /** @return list<TiKbArtigo> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('k.titulo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<TiKbArtigo> */
    public function search(Empresa $empresa, string $query, int $limit = 20): array
    {
        $q = '%' . mb_strtolower(trim($query)) . '%';

        return $this->createQueryBuilder('k')
            ->andWhere('k.empresa = :empresa')
            ->andWhere('LOWER(k.titulo) LIKE :q OR LOWER(k.resumo) LIKE :q OR LOWER(k.codigo) LIKE :q')
            ->setParameter('empresa', $empresa)
            ->setParameter('q', $q)
            ->orderBy('k.visualizacoes', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->andWhere('k.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function nextCodigoNumber(Empresa $empresa): int
    {
        $last = $this->createQueryBuilder('k')
            ->select('k.codigo')
            ->andWhere('k.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('k.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$last) {
            return 1;
        }

        return ((int) preg_replace('/\D/', '', (string) $last['codigo'])) + 1;
    }
}
