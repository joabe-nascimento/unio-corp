<?php

namespace App\Repository\Crm;

use App\Entity\Crm\CrmLead;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CrmLead> */
class CrmLeadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrmLead::class);
    }

    /** @return list<CrmLead> */
    public function findByEmpresa(Empresa $empresa, ?string $status = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('l.atualizadoEm', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null && $status !== '') {
            $qb->andWhere('l.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresa(Empresa $empresa, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa);

        if ($status !== null && $status !== '') {
            $qb->andWhere('l.status = :status')->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return array{total: int, by_status: array<string, int>} */
    public function countSummaryByEmpresa(Empresa $empresa): array
    {
        $byStatus = [];
        foreach (CrmLead::statusList() as $status) {
            $byStatus[$status] = $this->countByEmpresa($empresa, $status);
        }

        return [
            'total' => $this->countByEmpresa($empresa, null),
            'by_status' => $byStatus,
        ];
    }
}
