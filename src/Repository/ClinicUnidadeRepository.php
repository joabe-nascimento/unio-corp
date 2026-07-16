<?php

namespace App\Repository;

use App\Entity\ClinicUnidade;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicUnidade> */
class ClinicUnidadeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicUnidade::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicUnidade
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /**
     * @return list<ClinicUnidade>
     */
    public function findByEmpresa(Empresa $empresa, bool $onlyAtivos = false): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('u.nome', 'ASC');

        if ($onlyAtivos) {
            $qb->andWhere('u.ativo = true');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<ClinicUnidade> */
    public function findAtivasByEmpresa(Empresa $empresa): array
    {
        return $this->findByEmpresa($empresa, true);
    }
}
