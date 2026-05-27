<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhEsocialLote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhEsocialLote>
 */
class RhEsocialLoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhEsocialLote::class);
    }

    /** @return list<RhEsocialLote> */
    public function findForEmpresa(Empresa $empresa, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('l.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPendingByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.empresa = :empresa')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                RhEsocialLote::STATUS_PENDENTE,
                RhEsocialLote::STATUS_ERRO,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array{pendente: int, processando: int, enviado: int, erro: int}
     */
    public function countByStatusGrouped(Empresa $empresa): array
    {
        $defaults = [
            RhEsocialLote::STATUS_PENDENTE => 0,
            RhEsocialLote::STATUS_PROCESSANDO => 0,
            RhEsocialLote::STATUS_ENVIADO => 0,
            RhEsocialLote::STATUS_ERRO => 0,
        ];

        $rows = $this->createQueryBuilder('l')
            ->select('l.status AS status', 'COUNT(l.id) AS cnt')
            ->andWhere('l.empresa = :empresa')
            ->groupBy('l.status')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status !== '' && isset($defaults[$status])) {
                $defaults[$status] = (int) ($row['cnt'] ?? 0);
            }
        }

        return [
            'pendente' => $defaults[RhEsocialLote::STATUS_PENDENTE],
            'processando' => $defaults[RhEsocialLote::STATUS_PROCESSANDO],
            'enviado' => $defaults[RhEsocialLote::STATUS_ENVIADO],
            'erro' => $defaults[RhEsocialLote::STATUS_ERRO],
        ];
    }

    public function findPendingLote(Empresa $empresa, string $referencia, string $tipoEvento): ?RhEsocialLote
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.empresa = :empresa')
            ->andWhere('l.referencia = :ref')
            ->andWhere('l.tipoEvento = :tipo')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('ref', $referencia)
            ->setParameter('tipo', $tipoEvento)
            ->setParameter('statuses', [
                RhEsocialLote::STATUS_PENDENTE,
                RhEsocialLote::STATUS_PROCESSANDO,
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<RhEsocialLote> */
    public function findNextInQueue(?Empresa $empresa = null, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.status = :pendente OR (l.status = :erro AND l.tentativas < :maxTent)')
            ->setParameter('pendente', RhEsocialLote::STATUS_PENDENTE)
            ->setParameter('erro', RhEsocialLote::STATUS_ERRO)
            ->setParameter('maxTent', RhEsocialLote::MAX_TENTATIVAS)
            ->orderBy('l.criadoEm', 'ASC')
            ->setMaxResults($limit);

        if ($empresa !== null) {
            $qb->andWhere('l.empresa = :empresa')->setParameter('empresa', $empresa);
        }

        return $qb->getQuery()->getResult();
    }
}
