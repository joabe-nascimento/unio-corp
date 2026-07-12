<?php

namespace App\Repository;

use App\Entity\ClinicConvenio;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicConvenio> */
class ClinicConvenioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicConvenio::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicConvenio
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /**
     * @return list<ClinicConvenio>
     */
    public function findByEmpresa(Empresa $empresa, bool $onlyAtivos = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.nome', 'ASC');

        if ($onlyAtivos) {
            $qb->andWhere('c.ativo = true');
        }

        return $qb->getQuery()->getResult();
    }
}
