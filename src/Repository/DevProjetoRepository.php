<?php

namespace App\Repository;

use App\Entity\DevProjeto;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DevProjeto> */
class DevProjetoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevProjeto::class);
    }

    /** @return list<DevProjeto> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.atualizadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countEmAndamento(Empresa $empresa): int
    {
        return $this->countByStatus($empresa, DevProjeto::STATUS_EM_ANDAMENTO);
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(Empresa $empresa, string $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status = :s')
            ->setParameter('empresa', $empresa)
            ->setParameter('s', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<DevProjeto> */
    public function findRecentActive(Empresa $empresa, int $limit = 4): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [DevProjeto::STATUS_EM_ANDAMENTO, DevProjeto::STATUS_IDEIA])
            ->orderBy('p.atualizadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function averageProgressoAtivos(Empresa $empresa): int
    {
        $avg = $this->createQueryBuilder('p')
            ->select('AVG(p.progresso)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status = :s')
            ->setParameter('empresa', $empresa)
            ->setParameter('s', DevProjeto::STATUS_EM_ANDAMENTO)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) round((float) ($avg ?? 0));
    }
}
