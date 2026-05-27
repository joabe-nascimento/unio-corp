<?php

namespace App\Repository;

use App\Entity\Funcionario;
use App\Entity\RhComunicado;
use App\Entity\RhComunicadoLeitura;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhComunicadoLeitura>
 */
class RhComunicadoLeituraRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhComunicadoLeitura::class);
    }

    public function findOneByComunicadoAndFuncionario(RhComunicado $comunicado, Funcionario $funcionario): ?RhComunicadoLeitura
    {
        return $this->findOneBy(['comunicado' => $comunicado, 'funcionario' => $funcionario]);
    }
}
