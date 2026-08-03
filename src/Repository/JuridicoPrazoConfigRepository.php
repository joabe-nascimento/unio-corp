<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoPrazoConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoPrazoConfig> */
class JuridicoPrazoConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoPrazoConfig::class);
    }

    public function getOrCreate(Empresa $empresa): JuridicoPrazoConfig
    {
        $config = $this->findOneBy(['empresa' => $empresa]);
        if ($config instanceof JuridicoPrazoConfig) {
            return $config;
        }

        $config = new JuridicoPrazoConfig();
        $config->setEmpresa($empresa);
        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();

        return $config;
    }
}
