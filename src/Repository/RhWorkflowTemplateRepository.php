<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhWorkflowTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhWorkflowTemplate>
 */
class RhWorkflowTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhWorkflowTemplate::class);
    }

    /** @return list<RhWorkflowTemplate> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('w.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAtivosByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.empresa = :empresa')
            ->andWhere('w.ativo = true')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
