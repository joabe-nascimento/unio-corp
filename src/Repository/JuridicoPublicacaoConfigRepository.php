<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoPublicacaoConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoPublicacaoConfig> */
class JuridicoPublicacaoConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoPublicacaoConfig::class);
    }

    public function findByEmpresa(Empresa $empresa): ?JuridicoPublicacaoConfig
    {
        return $this->findOneBy(['empresa' => $empresa]);
    }

    public function getOrCreate(Empresa $empresa): JuridicoPublicacaoConfig
    {
        $config = $this->findByEmpresa($empresa);
        if ($config !== null) {
            return $config;
        }

        $config = (new JuridicoPublicacaoConfig())->setEmpresa($empresa);
        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();

        return $config;
    }
}
