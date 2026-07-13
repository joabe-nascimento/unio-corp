<?php

namespace App\Repository;

use App\Entity\ClinicConvenio;
use App\Entity\ClinicGuiaTiss;
use App\Entity\ClinicLoteTiss;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicLoteTiss> */
class ClinicLoteTissRepository extends ServiceEntityRepository
{
    public const LIST_LIMIT = 80;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicLoteTiss::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicLoteTiss
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /**
     * @return list<ClinicLoteTiss>
     */
    public function findByEmpresaAndStatus(Empresa $empresa, ?string $status = null, int $limit = self::LIST_LIMIT): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('l.criadoEm', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null && $status !== '' && $status !== 'todos') {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresaAndStatus(Empresa $empresa, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa);

        if ($status !== null && $status !== '' && $status !== 'todos') {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Guias elegíveis para incluir em um lote aberto do convênio.
     *
     * @return list<ClinicGuiaTiss>
     */
    public function findGuiasElegiveis(Empresa $empresa, ClinicConvenio $convenio, int $limit = 100): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('g')
            ->from(ClinicGuiaTiss::class, 'g')
            ->andWhere('g.empresa = :empresa')
            ->andWhere('g.convenio = :convenio')
            ->andWhere('g.lote IS NULL')
            ->andWhere('g.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('convenio', $convenio)
            ->setParameter('statuses', [
                ClinicGuiaTiss::STATUS_RASCUNHO,
                ClinicGuiaTiss::STATUS_ENVIADO,
            ])
            ->orderBy('g.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
