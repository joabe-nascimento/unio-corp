<?php

namespace App\Repository\Crm;

use App\Entity\Crm\CrmConta;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CrmConta> */
class CrmContaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrmConta::class);
    }

    /** @return list<CrmConta> */
    public function findByEmpresa(Empresa $empresa, ?string $status = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.atualizadoEm', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null && $status !== '') {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresa(Empresa $empresa, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa);

        if ($status !== null && $status !== '') {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return array{total: int, by_status: array<string, int>} */
    public function countSummaryByEmpresa(Empresa $empresa): array
    {
        $byStatus = [];
        foreach (CrmConta::statusList() as $status) {
            $byStatus[$status] = $this->countByEmpresa($empresa, $status);
        }

        return [
            'total' => $this->countByEmpresa($empresa, null),
            'by_status' => $byStatus,
        ];
    }
}
