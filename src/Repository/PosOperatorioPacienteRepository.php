<?php

namespace App\Repository;

use App\Entity\PosOperatorioPaciente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PosOperatorioPaciente> */
class PosOperatorioPacienteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PosOperatorioPaciente::class);
    }
}
