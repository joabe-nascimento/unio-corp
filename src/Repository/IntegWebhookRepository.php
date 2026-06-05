<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\IntegWebhook;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IntegWebhook> */
class IntegWebhookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegWebhook::class);
    }

    /** @return list<IntegWebhook> */
    public function findForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('w.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForEmpresa(Empresa $empresa, int $id): ?IntegWebhook
    {
        return $this->findOneBy(['empresa' => $empresa, 'id' => $id]);
    }

    public function countActiveForEmpresa(Empresa $empresa): int
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
