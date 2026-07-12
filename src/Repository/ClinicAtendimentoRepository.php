<?php

namespace App\Repository;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicAtendimento;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicAtendimento> */
class ClinicAtendimentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicAtendimento::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicAtendimento
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    public function findOneByAgendamento(Empresa $empresa, ClinicAgendamento $agendamento): ?ClinicAtendimento
    {
        return $this->findOneBy(['empresa' => $empresa, 'agendamento' => $agendamento]);
    }

    /**
     * @return list<ClinicAtendimento>
     */
    public function findRecentByEmpresa(Empresa $empresa, int $limit = 40): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('a.iniciadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
