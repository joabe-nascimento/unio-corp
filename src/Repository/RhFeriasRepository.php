<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhFerias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RhFerias> */
class RhFeriasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhFerias::class);
    }

    /** @return list<RhFerias> */
    public function findByEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->innerJoin('f.funcionario', 'func')
            ->addSelect('func')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('f.criadoEm', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('f.status = :status')->setParameter('status', $status);
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('func.nome LIKE :q OR func.email LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<RhFerias> */
    public function findByFuncionario(Funcionario $funcionario, int $limit = 24): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.funcionario = :funcionario')
            ->setParameter('funcionario', $funcionario)
            ->orderBy('f.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(Empresa $empresa, string $status): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasOverlap(Funcionario $funcionario, \DateTimeImmutable $inicio, \DateTimeImmutable $fim, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.funcionario = :funcionario')
            ->andWhere('f.status NOT IN (:cancelled)')
            ->andWhere('f.dataInicio <= :fim AND f.dataFim >= :inicio')
            ->setParameter('funcionario', $funcionario)
            ->setParameter('cancelled', [RhFerias::STATUS_REJEITADA, RhFerias::STATUS_CONCLUIDA])
            ->setParameter('inicio', $inicio)
            ->setParameter('fim', $fim);

        if ($excludeId !== null) {
            $qb->andWhere('f.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return list<RhFerias> */
    public function findPendingRecent(Empresa $empresa, int $limit = 5): array
    {
        return $this->createQueryBuilder('f')
            ->innerJoin('f.funcionario', 'func')
            ->addSelect('func')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhFerias::STATUS_SOLICITADA)
            ->orderBy('f.criadoEm', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<RhFerias> */
    public function findEmGozo(Empresa $empresa, int $limit = 5): array
    {
        return $this->createQueryBuilder('f')
            ->innerJoin('f.funcionario', 'func')
            ->addSelect('func')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhFerias::STATUS_EM_GOZO)
            ->orderBy('f.dataFim', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<RhFerias> */
    public function findReturnsSoon(Empresa $empresa, int $days = 14, int $limit = 6): array
    {
        $today = new \DateTimeImmutable('today');
        $until = $today->modify('+' . $days . ' days');

        return $this->createQueryBuilder('f')
            ->innerJoin('f.funcionario', 'func')
            ->addSelect('func')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.status IN (:statuses)')
            ->andWhere('f.dataFim >= :today')
            ->andWhere('f.dataFim <= :until')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [RhFerias::STATUS_EM_GOZO, RhFerias::STATUS_APROVADA])
            ->setParameter('today', $today)
            ->setParameter('until', $until)
            ->orderBy('f.dataFim', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<RhFerias> */
    public function findStartingSoon(Empresa $empresa, int $days = 14, int $limit = 6): array
    {
        $today = new \DateTimeImmutable('today');
        $until = $today->modify('+' . $days . ' days');

        return $this->createQueryBuilder('f')
            ->innerJoin('f.funcionario', 'func')
            ->addSelect('func')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.status = :status')
            ->andWhere('f.dataInicio >= :today')
            ->andWhere('f.dataInicio <= :until')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhFerias::STATUS_APROVADA)
            ->setParameter('today', $today)
            ->setParameter('until', $until)
            ->orderBy('f.dataInicio', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
