<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhOnboardingProcess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhOnboardingProcess>
 */
class RhOnboardingProcessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhOnboardingProcess::class);
    }

    /** @return list<RhOnboardingProcess> */
    public function findByEmpresa(Empresa $empresa, ?string $q = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.criadoEm', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('p.nome LIKE :q OR p.email LIKE :q OR p.cargo LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countOpenByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                RhOnboardingProcess::STATUS_RASCUNHO,
                RhOnboardingProcess::STATUS_EM_ANDAMENTO,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<RhOnboardingProcess> */
    public function findOpenRecent(Empresa $empresa, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                RhOnboardingProcess::STATUS_RASCUNHO,
                RhOnboardingProcess::STATUS_EM_ANDAMENTO,
            ])
            ->orderBy('p.atualizadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<RhOnboardingProcess> */
    public function findUpcomingStarts(Empresa $empresa, int $days = 30, int $limit = 4): array
    {
        $today = new \DateTimeImmutable('today');
        $until = $today->modify('+' . $days . ' days');

        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->andWhere('p.dataPrevista IS NOT NULL')
            ->andWhere('p.dataPrevista >= :today')
            ->andWhere('p.dataPrevista <= :until')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                RhOnboardingProcess::STATUS_RASCUNHO,
                RhOnboardingProcess::STATUS_EM_ANDAMENTO,
            ])
            ->setParameter('today', $today)
            ->setParameter('until', $until)
            ->orderBy('p.dataPrevista', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function averageOpenChecklistProgress(Empresa $empresa): int
    {
        $processes = $this->findOpenRecent($empresa, 50);
        if ($processes === []) {
            return 0;
        }
        $sum = 0;
        foreach ($processes as $p) {
            $sum += $p->checklistProgress();
        }

        return (int) round($sum / \count($processes));
    }

    public function countConcludedSince(Empresa $empresa, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status = :status')
            ->andWhere('p.dataConclusao >= :since')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhOnboardingProcess::STATUS_CONCLUIDO)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
