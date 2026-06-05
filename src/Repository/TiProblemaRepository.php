<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiProblema;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiProblema> */
class TiProblemaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiProblema::class);
    }

    /** @return list<TiProblema> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.atualizadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCodigo(Empresa $empresa, string $codigo): ?TiProblema
    {
        return $this->findOneBy(['empresa' => $empresa, 'codigo' => $codigo]);
    }

    public function nextCodigoNumber(Empresa $empresa): int
    {
        $last = $this->createQueryBuilder('p')
            ->select('p.codigo')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$last) {
            return 1;
        }

        return ((int) preg_replace('/\D/', '', (string) $last['codigo'])) + 1;
    }
}
