<?php

namespace App\Repository;

use App\Entity\ClinicTussCodigo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicTussCodigo> */
class ClinicTussCodigoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicTussCodigo::class);
    }

    /**
     * @return list<ClinicTussCodigo>
     */
    public function search(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.ativo = true')
            ->orderBy('t.codigo', 'ASC')
            ->setMaxResults($limit);

        if (preg_match('/^\d{3,}$/', $q)) {
            $qb->andWhere('t.codigo LIKE :q')
                ->setParameter('q', $q.'%');
        } else {
            $qb->andWhere('t.codigo LIKE :q OR LOWER(t.descricao) LIKE :like')
                ->setParameter('q', $q.'%')
                ->setParameter('like', '%'.mb_strtolower($q).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByCodigo(string $codigo): ?ClinicTussCodigo
    {
        return $this->findOneBy(['codigo' => $codigo, 'ativo' => true]);
    }
}
