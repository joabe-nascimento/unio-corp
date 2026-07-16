<?php

namespace App\Repository;

use App\Entity\ClinicPacote;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicPacote> */
class ClinicPacoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicPacote::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicPacote
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /**
     * @return list<ClinicPacote>
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
