<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoTemplatePeca;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoTemplatePeca> */
class JuridicoTemplatePecaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoTemplatePeca::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoTemplatePeca
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoTemplatePeca> */
    public function findForEmpresa(Empresa $empresa, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :e')
            ->setParameter('e', $empresa)
            ->orderBy('t.atualizadoEm', 'DESC')
            ->addOrderBy('t.criadoEm', 'DESC');

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('t.nome LIKE :q OR t.tipo LIKE :q OR t.area LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
