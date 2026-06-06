<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhVaga;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhVaga>
 */
class RhVagaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhVaga::class);
    }

    /** @return list<RhVaga> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('v.criadoEm', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('v.status = :status')->setParameter('status', $status);
        }

        $q = $q !== null ? trim($q) : '';
        if ($q !== '') {
            $qb->andWhere(
                'LOWER(v.titulo) LIKE :q OR LOWER(v.departamento) LIKE :q OR LOWER(v.descricao) LIKE :q',
            )->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneForEmpresa(int $id, Empresa $empresa): ?RhVaga
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.id = :id')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('id', $id)
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAbertasByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('v.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhVaga::STATUS_ABERTA)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPublicadasByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('v.status = :status')
            ->andWhere('v.publicadaEm IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhVaga::STATUS_ABERTA)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<RhVaga> */
    public function findPublicadasForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('v.status = :status')
            ->andWhere('v.publicadaEm IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhVaga::STATUS_ABERTA)
            ->orderBy('v.publicadaEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublicadaBySlug(Empresa $empresa, string $slug): ?RhVaga
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('v.slug = :slug')
            ->andWhere('v.status = :status')
            ->andWhere('v.publicadaEm IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->setParameter('slug', $slug)
            ->setParameter('status', RhVaga::STATUS_ABERTA)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<RhVaga> */
    public function findInscritiveisForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('v.status != :fechada')
            ->setParameter('empresa', $empresa)
            ->setParameter('fechada', RhVaga::STATUS_FECHADA)
            ->orderBy('v.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function vagaSlugExists(Empresa $empresa, string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('v.slug = :slug')
            ->setParameter('empresa', $empresa)
            ->setParameter('slug', $slug);
        if ($excludeId !== null) {
            $qb->andWhere('v.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return array<string, int> */
    public function countByStatusForEmpresa(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('v.status AS status, COUNT(v.id) AS total')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('v.status')
            ->getQuery()
            ->getArrayResult();

        $map = [
            RhVaga::STATUS_ABERTA => 0,
            RhVaga::STATUS_PAUSADA => 0,
            RhVaga::STATUS_FECHADA => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($map[$status])) {
                $map[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $map;
    }

    /** @return array{labels: list<string>, values: list<int>} */
    public function countGroupedByDepartamentoForEmpresa(Empresa $empresa, int $limit = 8): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('COALESCE(NULLIF(TRIM(v.departamento), \'\'), :sem) AS nome, COUNT(v.id) AS total')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->setParameter('sem', 'Sem departamento')
            ->groupBy('nome')
            ->orderBy('total', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getArrayResult();

        return $this->mapGroupedResult($rows);
    }

    /** @return array{labels: list<string>, values: list<int>} */
    public function countGroupedByLocalForEmpresa(Empresa $empresa, int $limit = 8): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('COALESCE(NULLIF(TRIM(v.localTrabalho), \'\'), :sem) AS nome, COUNT(v.id) AS total')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->setParameter('sem', 'Não informado')
            ->groupBy('nome')
            ->orderBy('total', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getArrayResult();

        return $this->mapGroupedResult($rows);
    }

    /** @param list<array<string, mixed>> $rows @return array{labels: list<string>, values: list<int>} */
    private function mapGroupedResult(array $rows): array
    {
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = (string) ($row['nome'] ?? '');
            $values[] = (int) ($row['total'] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
