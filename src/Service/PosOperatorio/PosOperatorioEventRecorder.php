<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class PosOperatorioEventRecorder
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function record(PosOperatorioPaciente $paciente, string $tipo, string $descricao, ?User $autor = null): void
    {
        $evento = (new PosOperatorioEvento())
            ->setPaciente($paciente)
            ->setTipo($tipo)
            ->setDescricao($descricao)
            ->setAutor($autor);

        $this->em->persist($evento);
    }
}
