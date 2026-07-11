<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\PosOperatorioEvento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PosOperatorioEvento> */
class PosOperatorioEventoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PosOperatorioEvento::class);
    }

    /** @return list<PosOperatorioEvento> */
    public function findRecentByEmpresa(Empresa $empresa, int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->innerJoin('e.paciente', 'p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('e.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function hasLembreteOnDate(\App\Entity\PosOperatorioPaciente $paciente, \DateTimeImmutable $day): bool
    {
        return $this->hasTipoOnDate($paciente, PosOperatorioEvento::TIPO_LEMBRETE, $day);
    }

    public function hasRetornoConfirmadoOnDate(\App\Entity\PosOperatorioPaciente $paciente, \DateTimeImmutable $day): bool
    {
        return $this->hasTipoOnDate($paciente, PosOperatorioEvento::TIPO_RETORNO, $day);
    }

    public function hasTipoOnDate(\App\Entity\PosOperatorioPaciente $paciente, string $tipo, \DateTimeImmutable $day): bool
    {
        $start = $day->setTime(0, 0);
        $end = $start->modify('+1 day');

        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.paciente = :paciente')
            ->andWhere('e.tipo = :tipo')
            ->andWhere('e.criadoEm >= :start')
            ->andWhere('e.criadoEm < :end')
            ->setParameter('paciente', $paciente)
            ->setParameter('tipo', $tipo)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /** @return list<PosOperatorioEvento> */
    public function findVisibleToPatient(\App\Entity\PosOperatorioPaciente $paciente, int $limit = 15): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.paciente = :paciente')
            ->andWhere('e.tipo IN (:tipos)')
            ->setParameter('paciente', $paciente)
            ->setParameter('tipos', PosOperatorioEvento::TIPOS_VISIVEIS_PACIENTE)
            ->orderBy('e.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
