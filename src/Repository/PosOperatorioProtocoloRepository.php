<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\PosOperatorioProtocolo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PosOperatorioProtocolo> */
class PosOperatorioProtocoloRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PosOperatorioProtocolo::class);
    }

    /** @return list<PosOperatorioProtocolo> */
    public function findAtivosByEmpresa(Empresa $empresa): array
    {
        return $this->findBy(['empresa' => $empresa, 'ativo' => true], ['nome' => 'ASC']);
    }

    public function countAtivosByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->andWhere('pr.empresa = :empresa')
            ->andWhere('pr.ativo = true')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
