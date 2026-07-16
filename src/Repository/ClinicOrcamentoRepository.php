<?php

namespace App\Repository;

use App\Entity\ClinicOrcamento;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicOrcamento> */
class ClinicOrcamentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicOrcamento::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicOrcamento
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /**
     * @return list<ClinicOrcamento>
     */
    public function findByEmpresa(Empresa $empresa, bool $onlyAtivos = false): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
