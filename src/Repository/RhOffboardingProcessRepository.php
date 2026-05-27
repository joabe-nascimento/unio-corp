<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhOffboardingProcess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhOffboardingProcess>
 */
class RhOffboardingProcessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhOffboardingProcess::class);
    }

    /** @return list<RhOffboardingProcess> */
    public function findByEmpresa(Empresa $empresa, ?string $q = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.funcionario', 'f')
            ->addSelect('f')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.criadoEm', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('f.nome LIKE :q OR f.email LIKE :q OR p.motivo LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countOpenByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                RhOffboardingProcess::STATUS_RASCUNHO,
                RhOffboardingProcess::STATUS_EM_ANDAMENTO,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasOpenProcessForFuncionario(Funcionario $funcionario): bool
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.funcionario = :funcionario')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('funcionario', $funcionario)
            ->setParameter('statuses', [
                RhOffboardingProcess::STATUS_RASCUNHO,
                RhOffboardingProcess::STATUS_EM_ANDAMENTO,
            ])
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /** @return list<RhOffboardingProcess> */
    public function findOpenRecent(Empresa $empresa, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.funcionario', 'f')
            ->addSelect('f')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                RhOffboardingProcess::STATUS_RASCUNHO,
                RhOffboardingProcess::STATUS_EM_ANDAMENTO,
            ])
            ->orderBy('p.atualizadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
