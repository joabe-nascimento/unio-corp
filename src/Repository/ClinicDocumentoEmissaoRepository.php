<?php

namespace App\Repository;

use App\Entity\ClinicDocumentoEmissao;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicDocumentoEmissao> */
class ClinicDocumentoEmissaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicDocumentoEmissao::class);
    }

    /** @return list<ClinicDocumentoEmissao> */
    public function findByPaciente(PosOperatorioPaciente $paciente, ?string $tipo = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.paciente = :paciente')
            ->setParameter('paciente', $paciente)
            ->orderBy('e.criadoEm', 'DESC');

        if ($tipo !== null) {
            $qb->andWhere('e.tipo = :tipo')->setParameter('tipo', $tipo);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<ClinicDocumentoEmissao> */
    public function findRecentByEmpresa(Empresa $empresa, int $limit = 100): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('e.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
