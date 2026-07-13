<?php

namespace App\Repository\Crm;

use App\Entity\Crm\CrmOportunidade;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CrmOportunidade> */
class CrmOportunidadeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrmOportunidade::class);
    }

    /** @return list<CrmOportunidade> */
    public function findByEmpresa(Empresa $empresa, int $limit = 200): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('o.atualizadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresaAndStage(Empresa $empresa, string $estagio): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.empresa = :empresa')
            ->andWhere('o.estagio = :estagio')
            ->setParameter('empresa', $empresa)
            ->setParameter('estagio', $estagio)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumValorAberto(Empresa $empresa): float
    {
        $val = $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.valor), 0)')
            ->andWhere('o.empresa = :empresa')
            ->andWhere('o.estagio NOT IN (:fechados)')
            ->setParameter('empresa', $empresa)
            ->setParameter('fechados', [CrmOportunidade::STAGE_GANHO, CrmOportunidade::STAGE_PERDIDO])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $val;
    }
}
