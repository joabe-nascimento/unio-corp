<?php

namespace App\Repository;

use App\Entity\ClinicEmpresaConfig;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicEmpresaConfig> */
class ClinicEmpresaConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicEmpresaConfig::class);
    }

    public function findForEmpresa(Empresa $empresa): ?ClinicEmpresaConfig
    {
        return $this->findOneBy(['empresa' => $empresa]);
    }
}
