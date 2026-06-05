<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegLog> */
class IntegLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegLog::class);
    }

    /** @return list<IntegLog> */
    public function findRecentForEmpresa(Empresa $empresa, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('l.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countTodayForEmpresa(Empresa $empresa): int
    {
        $start = (new \DateTimeImmutable('today'));

        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.empresa = :empresa')
            ->andWhere('l.criadoEm >= :start')
            ->setParameter('empresa', $empresa)
            ->setParameter('start', $start)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countErrorsOpen(Empresa $empresa): int
    {
        $since = (new \DateTimeImmutable('-24 hours'));

        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.empresa = :empresa')
            ->andWhere('l.nivel = :error')
            ->andWhere('l.criadoEm >= :since')
            ->setParameter('empresa', $empresa)
            ->setParameter('error', IntegLog::LEVEL_ERROR)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array{total: int, page: int, limit: int, items: list<IntegLog>} */
    public function findForEmpresaFiltered(Empresa $empresa, array $filters = [], int $page = 1, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('l.criadoEm', 'DESC');

        if (!empty($filters['nivel'])) {
            $qb->andWhere('l.nivel = :nivel')->setParameter('nivel', $filters['nivel']);
        }
        if (!empty($filters['origem'])) {
            $qb->andWhere('l.origem LIKE :origem')->setParameter('origem', '%' . $filters['origem'] . '%');
        }
        if (!empty($filters['flow_key'])) {
            $qb->andWhere('l.traceId LIKE :flow')->setParameter('flow', '%' . $filters['flow_key'] . '%');
        }
        if (!empty($filters['data_inicio'])) {
            $qb->andWhere('l.criadoEm >= :di')->setParameter('di', new \DateTimeImmutable($filters['data_inicio']));
        }

        $total = (int) (clone $qb)->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult();

        return ['total' => $total, 'page' => $page, 'limit' => $limit, 'items' => $items];
    }

    /** @return list<IntegLog> */
    public function exportCsv(Empresa $empresa): array
    {
        return $this->findBy(['empresa' => $empresa], ['criadoEm' => 'DESC'], 1000);
    }
}
