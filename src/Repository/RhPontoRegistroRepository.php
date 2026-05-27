<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhPontoRegistro;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhPontoRegistro>
 */
class RhPontoRegistroRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhPontoRegistro::class);
    }

    /** @return list<RhPontoRegistro> */
    public function findByFuncionarioAndDate(Funcionario $funcionario, \DateTimeImmutable $date): array
    {
        $start = $date->setTime(0, 0);
        $end = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->andWhere('p.funcionario = :funcionario')
            ->andWhere('p.registradoEm >= :start')
            ->andWhere('p.registradoEm <= :end')
            ->setParameter('funcionario', $funcionario)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.registradoEm', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countHojeByEmpresa(Empresa $empresa): int
    {
        $hoje = new \DateTimeImmutable('today');
        $fim = $hoje->setTime(23, 59, 59);

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.registradoEm >= :start')
            ->andWhere('p.registradoEm <= :end')
            ->setParameter('empresa', $empresa)
            ->setParameter('start', $hoje)
            ->setParameter('end', $fim)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
