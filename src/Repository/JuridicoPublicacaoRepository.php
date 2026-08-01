<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoPublicacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoPublicacao> */
class JuridicoPublicacaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoPublicacao::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoPublicacao
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    public function findByDjenId(Empresa $empresa, int $djenId): ?JuridicoPublicacao
    {
        return $this->findOneBy(['empresa' => $empresa, 'djenId' => $djenId]);
    }

    /** @return list<JuridicoPublicacao> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $prioridade = null, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.processo', 'proc')->addSelect('proc')
            ->leftJoin('p.cliente', 'cli')->addSelect('cli')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.dataDisponibilizacao', 'DESC')
            ->addOrderBy('p.criadoEm', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        if ($prioridade !== null && $prioridade !== '') {
            $qb->andWhere('p.prioridade = :prioridade')->setParameter('prioridade', $prioridade);
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('p.numeroProcesso LIKE :q OR p.texto LIKE :q OR p.tipoComunicacao LIKE :q OR p.tribunal LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countNaoLidas(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', JuridicoPublicacao::STATUS_NAO_LIDA)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTriagemPendente(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->andWhere('p.iaResumo IS NULL')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                JuridicoPublicacao::STATUS_NAO_LIDA,
                JuridicoPublicacao::STATUS_TRIAGEM,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countVinculadas(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', JuridicoPublicacao::STATUS_VINCULADA)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAtivas(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status NOT IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                JuridicoPublicacao::STATUS_ARQUIVADA,
                JuridicoPublicacao::STATUS_CANCELADA,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
