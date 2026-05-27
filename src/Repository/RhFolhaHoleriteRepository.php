<?php

namespace App\Repository;

use App\Entity\Funcionario;
use App\Entity\RhFolhaCompetencia;
use App\Entity\RhFolhaHolerite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhFolhaHolerite>
 */
class RhFolhaHoleriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhFolhaHolerite::class);
    }

    /** @return list<RhFolhaHolerite> */
    public function findByFuncionario(Funcionario $funcionario, int $limit = 12, bool $somenteFechadas = true): array
    {
        $qb = $this->createQueryBuilder('h')
            ->join('h.competencia', 'c')
            ->addSelect('c')
            ->andWhere('h.funcionario = :funcionario')
            ->setParameter('funcionario', $funcionario)
            ->orderBy('c.referencia', 'DESC')
            ->setMaxResults($limit);

        if ($somenteFechadas) {
            $qb->andWhere('c.status = :fechada')->setParameter('fechada', 'FECHADA');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneForFuncionario(int $holeriteId, Funcionario $funcionario): ?RhFolhaHolerite
    {
        return $this->createQueryBuilder('h')
            ->join('h.competencia', 'c')
            ->addSelect('c')
            ->andWhere('h.id = :id')
            ->andWhere('h.funcionario = :funcionario')
            ->andWhere('c.status = :fechada')
            ->setParameter('id', $holeriteId)
            ->setParameter('funcionario', $funcionario)
            ->setParameter('fechada', 'FECHADA')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByCompetenciaAndFuncionario(RhFolhaCompetencia $competencia, Funcionario $funcionario): ?RhFolhaHolerite
    {
        return $this->findOneBy(['competencia' => $competencia, 'funcionario' => $funcionario]);
    }

    public function countByCompetencia(RhFolhaCompetencia $competencia): int
    {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.competencia = :competencia')
            ->setParameter('competencia', $competencia)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
