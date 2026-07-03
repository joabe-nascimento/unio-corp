<?php

namespace App\Repository;

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

    public function hasLembreteOnDate(\App\Entity\PosOperatorioPaciente $paciente, \DateTimeImmutable $day): bool
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
            ->setParameter('tipo', PosOperatorioEvento::TIPO_LEMBRETE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
