<?php

namespace App\Repository;

use App\Entity\PlatformAuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformAuditLog>
 */
class PlatformAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformAuditLog::class);
    }

    /**
     * @return array{items: list<PlatformAuditLog>, total: int}
     */
    public function paginate(
        int $page,
        int $perPage,
        string $categoria = '',
        string $acao = '',
        string $resultado = '',
        string $search = '',
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.criadoEm', 'DESC');

        if ($categoria !== '') {
            $qb->andWhere('a.categoria = :categoria')->setParameter('categoria', $categoria);
        }
        if ($acao !== '') {
            $qb->andWhere('a.acao = :acao')->setParameter('acao', $acao);
        }
        if ($resultado !== '') {
            $qb->andWhere('a.resultado = :resultado')->setParameter('resultado', $resultado);
        }
        if ($search !== '') {
            $qb->andWhere(
                'a.mensagem LIKE :q OR a.actorEmail LIKE :q OR a.actorNome LIKE :q OR a.alvoRotulo LIKE :q'
            )->setParameter('q', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /** @return array{success: int, failure: int, warning: int} */
    public function countOutcomesSince(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.resultado AS resultado, COUNT(a.id) AS total')
            ->andWhere('a.criadoEm >= :since')
            ->setParameter('since', $since)
            ->groupBy('a.resultado')
            ->getQuery()
            ->getArrayResult();

        $counts = ['success' => 0, 'failure' => 0, 'warning' => 0];
        foreach ($rows as $row) {
            $key = (string) ($row['resultado'] ?? '');
            if (isset($counts[$key])) {
                $counts[$key] = (int) $row['total'];
            }
        }

        return $counts;
    }

    /** @return array<string, int> */
    public function countByCategorySince(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.categoria AS categoria, COUNT(a.id) AS total')
            ->andWhere('a.criadoEm >= :since')
            ->setParameter('since', $since)
            ->groupBy('a.categoria')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['categoria']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return list<PlatformAuditLog> */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
