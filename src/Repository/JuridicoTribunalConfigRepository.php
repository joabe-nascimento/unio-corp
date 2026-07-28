<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoTribunalConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoTribunalConfig> */
class JuridicoTribunalConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoTribunalConfig::class);
    }

    public function findByEmpresa(Empresa $empresa): ?JuridicoTribunalConfig
    {
        return $this->findOneBy(['empresa' => $empresa]);
    }
}
