<?php

namespace App\Repository\Organismo;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoCareContract;
use App\Entity\PosOperatorioPaciente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrganismoCareContract> */
class OrganismoCareContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganismoCareContract::class);
    }

    public function findActiveForPaciente(PosOperatorioPaciente $paciente): ?OrganismoCareContract
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.paciente = :paciente')
            ->andWhere('c.ativo = true')
            ->setParameter('paciente', $paciente)
            ->orderBy('c.versao', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<OrganismoCareContract> */
    public function findActiveByEmpresa(Empresa $empresa, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.ativo = true')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.atualizadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function nextVersion(PosOperatorioPaciente $paciente): int
    {
        $max = $this->createQueryBuilder('c')
            ->select('MAX(c.versao)')
            ->andWhere('c.paciente = :paciente')
            ->setParameter('paciente', $paciente)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }
}
