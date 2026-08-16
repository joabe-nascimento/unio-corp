<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoComplianceIncidente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoComplianceIncidente> */
class JuridicoComplianceIncidenteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoComplianceIncidente::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoComplianceIncidente
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoComplianceIncidente> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->findBy(['empresa' => $empresa], ['criadoEm' => 'DESC']);
    }

    public function countAbertos(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.empresa = :e')
            ->andWhere('i.status != :s')
            ->setParameter('e', $empresa)
            ->setParameter('s', JuridicoComplianceIncidente::STATUS_RESOLVIDO)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
