<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoJurisprudencia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoJurisprudencia> */
class JuridicoJurisprudenciaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoJurisprudencia::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoJurisprudencia
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoJurisprudencia> */
    public function findForEmpresa(Empresa $empresa, ?string $relevancia = null, ?string $q = null, ?string $tribunal = null, bool $apenasFavoritos = false): array
    {
        $qb = $this->createQueryBuilder('j')
            ->andWhere('j.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('j.favorito', 'DESC')
            ->addOrderBy('j.criadoEm', 'DESC');

        if ($relevancia !== null && $relevancia !== '') {
            $qb->andWhere('j.relevancia = :relevancia')->setParameter('relevancia', $relevancia);
        }

        if ($tribunal !== null && $tribunal !== '') {
            $qb->andWhere('j.tribunal = :tribunal')->setParameter('tribunal', $tribunal);
        }

        if ($apenasFavoritos) {
            $qb->andWhere('j.favorito = true');
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('j.tema LIKE :q OR j.tribunal LIKE :q OR j.referencia LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countFavoritosByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.empresa = :empresa')
            ->andWhere('j.favorito = true')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
