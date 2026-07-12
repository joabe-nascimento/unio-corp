<?php

namespace App\Repository;

use App\Entity\ClinicConta;
use App\Entity\ClinicGuiaTiss;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicGuiaTiss> */
class ClinicGuiaTissRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicGuiaTiss::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicGuiaTiss
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    public function findOneByConta(Empresa $empresa, ClinicConta $conta): ?ClinicGuiaTiss
    {
        return $this->findOneBy(['empresa' => $empresa, 'conta' => $conta]);
    }

    /**
     * @return list<ClinicGuiaTiss>
     */
    public function findByEmpresaAndStatus(Empresa $empresa, ?string $status = null, int $limit = 80): array
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('g.criadoEm', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null && $status !== '' && $status !== 'todos') {
            $qb->andWhere('g.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}
