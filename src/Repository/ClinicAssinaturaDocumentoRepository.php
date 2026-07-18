<?php

namespace App\Repository;

use App\Entity\ClinicAssinaturaDocumento;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicAssinaturaDocumento> */
class ClinicAssinaturaDocumentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicAssinaturaDocumento::class);
    }

    /** @return list<ClinicAssinaturaDocumento> */
    public function findOpenByEmpresa(Empresa $empresa, ?string $status = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('d.criadoEm', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null && $status !== '') {
            $qb->andWhere('d.status = :status')->setParameter('status', $status);
        } else {
            $qb->andWhere('d.status NOT IN (:closed)')
                ->setParameter('closed', [
                    ClinicAssinaturaDocumento::STATUS_CONCLUIDA,
                    ClinicAssinaturaDocumento::STATUS_CANCELADA,
                ]);
        }

        return $qb->getQuery()->getResult();
    }

    public function countOpenByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.empresa = :empresa')
            ->andWhere('d.status != :done')
            ->andWhere('d.status != :cancel')
            ->setParameter('empresa', $empresa)
            ->setParameter('done', ClinicAssinaturaDocumento::STATUS_CONCLUIDA)
            ->setParameter('cancel', ClinicAssinaturaDocumento::STATUS_CANCELADA)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string, int> */
    public function countByStatusForEmpresa(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.status AS status, COUNT(d.id) AS total')
            ->andWhere('d.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('d.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }
}
