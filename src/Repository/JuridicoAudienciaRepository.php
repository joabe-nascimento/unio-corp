<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoAudiencia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoAudiencia> */
class JuridicoAudienciaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoAudiencia::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoAudiencia
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoAudiencia> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.processo', 'p')->addSelect('p')
            ->leftJoin('a.responsavel', 'r')->addSelect('r')
            ->andWhere('a.empresa = :e')
            ->setParameter('e', $empresa)
            ->orderBy('a.dataHora', 'ASC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('a.status = :s')->setParameter('s', $status);
        }
        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('a.tipo LIKE :q OR a.local LIKE :q OR p.numero LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countAgendadas(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :e')
            ->andWhere('a.status = :s')
            ->setParameter('e', $empresa)
            ->setParameter('s', JuridicoAudiencia::STATUS_AGENDADA)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
