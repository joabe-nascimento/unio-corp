<?php

namespace App\Repository\Organismo;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoDayTwinRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrganismoDayTwinRun> */
class OrganismoDayTwinRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganismoDayTwinRun::class);
    }

    public function findForDay(Empresa $empresa, \DateTimeImmutable $dia): ?OrganismoDayTwinRun
    {
        return $this->findOneBy([
            'empresa' => $empresa,
            'dia' => $dia->setTime(0, 0),
        ]);
    }
}
