<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoAtendimentoTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoAtendimentoTemplate> */
class JuridicoAtendimentoTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoAtendimentoTemplate::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoAtendimentoTemplate
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoAtendimentoTemplate> */
    public function findAtivosForEmpresa(Empresa $empresa, ?string $area = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :empresa')
            ->andWhere('t.ativo = true')
            ->setParameter('empresa', $empresa)
            ->orderBy('t.titulo', 'ASC');

        if ($area !== null && $area !== '') {
            $qb->andWhere('t.area = :area OR t.area IS NULL')
                ->setParameter('area', $area);
        }

        return $qb->getQuery()->getResult();
    }

    public function countForEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.empresa = :empresa')
            ->andWhere('t.ativo = true')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
