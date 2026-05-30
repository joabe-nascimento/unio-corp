<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\InovIdeia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InovIdeia> */
class InovIdeiaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InovIdeia::class);
    }

    /** @return list<InovIdeia> */
    public function findByEmpresa(Empresa $empresa, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('i.atualizadoEm', 'DESC');

        if (!$includeArchived) {
            $qb->andWhere('i.arquivado = false');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?InovIdeia
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByEmpresaAndEstagio(Empresa $empresa, string $estagio): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.empresa = :empresa')
            ->andWhere('i.estagio = :estagio')
            ->andWhere('i.arquivado = false')
            ->setParameter('empresa', $empresa)
            ->setParameter('estagio', $estagio)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, list<InovIdeia>>
     */
    public function findActivePipeline(Empresa $empresa): array
    {
        $stages = [
            InovIdeia::STAGE_IDEIA,
            InovIdeia::STAGE_HIPOTESE,
            InovIdeia::STAGE_POC,
            InovIdeia::STAGE_PILOTO,
            InovIdeia::STAGE_ESCALA,
        ];

        $pipeline = array_fill_keys($stages, []);

        $items = $this->createQueryBuilder('i')
            ->andWhere('i.empresa = :empresa')
            ->andWhere('i.arquivado = false')
            ->andWhere('i.estagio IN (:stages)')
            ->setParameter('empresa', $empresa)
            ->setParameter('stages', $stages)
            ->orderBy('i.atualizadoEm', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($items as $item) {
            $stage = $item->getEstagio();
            if (isset($pipeline[$stage])) {
                $pipeline[$stage][] = $item;
            }
        }

        return $pipeline;
    }

    public function countByCodigoPrefix(Empresa $empresa, string $prefix): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.empresa = :empresa')
            ->andWhere('i.codigo LIKE :prefix')
            ->setParameter('empresa', $empresa)
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUpdatedSince(Empresa $empresa, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.empresa = :empresa')
            ->andWhere('i.arquivado = false')
            ->andWhere('i.atualizadoEm >= :since')
            ->setParameter('empresa', $empresa)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUpdatedBetween(Empresa $empresa, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.empresa = :empresa')
            ->andWhere('i.arquivado = false')
            ->andWhere('i.atualizadoEm >= :from')
            ->andWhere('i.atualizadoEm < :to')
            ->setParameter('empresa', $empresa)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function averageCycleDays(Empresa $empresa): int
    {
        /** @var list<InovIdeia> $items */
        $items = $this->createQueryBuilder('i')
            ->andWhere('i.empresa = :empresa')
            ->andWhere('i.estagio NOT IN (:stages)')
            ->setParameter('empresa', $empresa)
            ->setParameter('stages', [InovIdeia::STAGE_IDEIA, InovIdeia::STAGE_ARQUIVADO, InovIdeia::STAGE_KILL])
            ->getQuery()
            ->getResult();

        if ($items === []) {
            return 0;
        }

        $totalDays = array_sum(array_map(static fn (InovIdeia $i) => $i->getDaysOpen(), $items));

        return (int) round($totalDays / \count($items));
    }

    /** @return list<InovIdeia> */
    public function findRecentlyUpdated(Empresa $empresa, int $limit = 5): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.empresa = :empresa')
            ->andWhere('i.arquivado = false')
            ->setParameter('empresa', $empresa)
            ->orderBy('i.atualizadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<string> $estagios
     * @return list<InovIdeia>
     */
    public function findByEstagios(Empresa $empresa, array $estagios): array
    {
        if ($estagios === []) {
            return [];
        }

        return $this->createQueryBuilder('i')
            ->andWhere('i.empresa = :empresa')
            ->andWhere('i.arquivado = false')
            ->andWhere('i.estagio IN (:estagios)')
            ->setParameter('empresa', $empresa)
            ->setParameter('estagios', $estagios)
            ->orderBy('i.atualizadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
