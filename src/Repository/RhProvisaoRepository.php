<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhProvisao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhProvisao>
 */
class RhProvisaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhProvisao::class);
    }

    /** @return list<RhProvisao> */
    public function findForEmpresa(Empresa $empresa, ?string $referencia = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.referencia', 'DESC')
            ->addOrderBy('p.tipo', 'ASC');

        if ($referencia !== null && $referencia !== '') {
            $qb->andWhere('p.referencia = :ref')->setParameter('ref', $referencia);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByEmpresaRefTipo(Empresa $empresa, string $referencia, string $tipo): ?RhProvisao
    {
        return $this->findOneBy(['empresa' => $empresa, 'referencia' => $referencia, 'tipo' => $tipo]);
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
}
