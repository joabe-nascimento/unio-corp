<?php

namespace App\Repository;

use App\Entity\ClinicConta;
use App\Entity\ClinicGuiaTiss;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicGuiaTiss> */
class ClinicGuiaTissRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicGuiaTiss::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicGuiaTiss
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    public function findOneByConta(Empresa $empresa, ClinicConta $conta): ?ClinicGuiaTiss
    {
        return $this->findOneBy(['empresa' => $empresa, 'conta' => $conta]);
    }

    public const LIST_LIMIT = 80;

    /**
     * @return list<ClinicGuiaTiss>
     */
    public function findByEmpresaAndStatus(Empresa $empresa, ?string $status = null, int $limit = self::LIST_LIMIT): array
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('g.criadoEm', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null && $status !== '' && $status !== 'todos') {
            $qb->andWhere('g.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresaAndStatus(Empresa $empresa, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.empresa = :empresa')
            ->setParameter('empresa', $empresa);

        if ($status !== null && $status !== '' && $status !== 'todos') {
            $qb->andWhere('g.status = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<array{convenio_id: int|null, convenio: string, total: int, glosadas: int, pagas: int, taxa_glosa: float}>
     */
    public function glosaStatsByConvenio(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('g')
            ->select('IDENTITY(g.convenio) AS convenio_id')
            ->addSelect('COALESCE(c.nome, \'Sem convênio\') AS convenio_nome')
            ->addSelect('COUNT(g.id) AS total')
            ->addSelect('SUM(CASE WHEN g.status = :glosado THEN 1 ELSE 0 END) AS glosadas')
            ->addSelect('SUM(CASE WHEN g.status = :pago THEN 1 ELSE 0 END) AS pagas')
            ->leftJoin('g.convenio', 'c')
            ->andWhere('g.empresa = :empresa')
            ->andWhere('g.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('glosado', ClinicGuiaTiss::STATUS_GLOSADO)
            ->setParameter('pago', ClinicGuiaTiss::STATUS_PAGO)
            ->setParameter('statuses', [
                ClinicGuiaTiss::STATUS_ENVIADO,
                ClinicGuiaTiss::STATUS_AUTORIZADO,
                ClinicGuiaTiss::STATUS_GLOSADO,
                ClinicGuiaTiss::STATUS_PAGO,
            ])
            ->groupBy('g.convenio')
            ->addGroupBy('c.nome')
            ->orderBy('glosadas', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $total = (int) ($row['total'] ?? 0);
            $glosadas = (int) ($row['glosadas'] ?? 0);
            $out[] = [
                'convenio_id' => isset($row['convenio_id']) ? (int) $row['convenio_id'] : null,
                'convenio' => (string) ($row['convenio_nome'] ?? 'Sem convênio'),
                'total' => $total,
                'glosadas' => $glosadas,
                'pagas' => (int) ($row['pagas'] ?? 0),
                'taxa_glosa' => $total > 0 ? round(($glosadas / $total) * 100, 1) : 0.0,
            ];
        }

        return $out;
    }
}
