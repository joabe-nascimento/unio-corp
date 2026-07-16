<?php

namespace App\Repository;

use App\Entity\ClinicEstoqueItem;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicEstoqueItem> */
class ClinicEstoqueItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicEstoqueItem::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicEstoqueItem
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /**
     * @return list<ClinicEstoqueItem>
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
