<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\PessoasAvaliacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PessoasAvaliacao> */
class PessoasAvaliacaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PessoasAvaliacao::class);
    }

    /** @return list<PessoasAvaliacao> */
    public function findByEmpresa(Empresa $empresa, ?int $funcionarioId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('a.criadoEm', 'DESC');

        if ($funcionarioId !== null && $funcionarioId > 0) {
            $qb->andWhere('a.funcionario = :funcionario')
                ->setParameter('funcionario', $funcionarioId);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<PessoasAvaliacao> */
    public function findByFuncionario(Funcionario $funcionario): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.funcionario = :funcionario')
            ->setParameter('funcionario', $funcionario)
            ->orderBy('a.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
