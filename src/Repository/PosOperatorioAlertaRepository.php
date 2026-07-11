<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\PosOperatorioAlerta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PosOperatorioAlerta> */
class PosOperatorioAlertaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PosOperatorioAlerta::class);
    }

    /** @return list<PosOperatorioAlerta> */
    public function findAbertosByEmpresa(Empresa $empresa, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [PosOperatorioAlerta::STATUS_ABERTO, PosOperatorioAlerta::STATUS_EM_ATENDIMENTO])
            ->orderBy('a.prioridade', 'ASC')
            ->addOrderBy('a.criadoEm', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAbertosByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [PosOperatorioAlerta::STATUS_ABERTO, PosOperatorioAlerta::STATUS_EM_ATENDIMENTO])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<PosOperatorioAlerta> */
    public function findQueueByEmpresa(Empresa $empresa, ?string $prioridade = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('a.prioridade', 'ASC')
            ->addOrderBy('a.criadoEm', 'ASC');

        if ($status === 'ativos' || $status === null) {
            $qb->andWhere('a.status IN (:statuses)')
                ->setParameter('statuses', [PosOperatorioAlerta::STATUS_ABERTO, PosOperatorioAlerta::STATUS_EM_ATENDIMENTO]);
        } elseif ($status !== 'todos') {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }

        if ($prioridade !== null && $prioridade !== 'todas') {
            $qb->andWhere('a.prioridade = :pri')->setParameter('pri', $prioridade);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<PosOperatorioAlerta> */
    public function findP1Ativos(Empresa $empresa): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.prioridade = :p1')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('p1', 'P1')
            ->setParameter('statuses', [PosOperatorioAlerta::STATUS_ABERTO, PosOperatorioAlerta::STATUS_EM_ATENDIMENTO])
            ->orderBy('a.criadoEm', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<PosOperatorioAlerta> */
    public function findForExportByEmpresa(Empresa $empresa, int $limit = 5000): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.paciente', 'p')
            ->addSelect('p')
            ->andWhere('a.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('a.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAbertosByPaciente(\App\Entity\PosOperatorioPaciente $paciente, ?int $exceptId = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.paciente = :paciente')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('paciente', $paciente)
            ->setParameter('statuses', [PosOperatorioAlerta::STATUS_ABERTO, PosOperatorioAlerta::STATUS_EM_ATENDIMENTO]);

        if ($exceptId !== null) {
            $qb->andWhere('a.id != :except')->setParameter('except', $exceptId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function hasOpenAlertWithMotivo(\App\Entity\PosOperatorioPaciente $paciente, string $motivoContains): bool
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.paciente = :paciente')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.motivo LIKE :motivo')
            ->setParameter('paciente', $paciente)
            ->setParameter('statuses', [PosOperatorioAlerta::STATUS_ABERTO, PosOperatorioAlerta::STATUS_EM_ATENDIMENTO])
            ->setParameter('motivo', '%' . $motivoContains . '%')
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
