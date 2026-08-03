<?php

namespace App\Repository;

use App\Entity\JuridicoPrazo;
use App\Entity\JuridicoPrazoAlertaLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoPrazoAlertaLog> */
class JuridicoPrazoAlertaLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoPrazoAlertaLog::class);
    }

    public function jaEnviado(JuridicoPrazo $prazo, string $nivel, string $canal): bool
    {
        return $this->count([
            'prazo' => $prazo,
            'nivel' => $nivel,
            'canal' => $canal,
        ]) > 0;
    }
}
