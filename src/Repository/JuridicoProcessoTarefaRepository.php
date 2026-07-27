<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoProcesso;
use App\Entity\JuridicoProcessoTarefa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoProcessoTarefa> */
class JuridicoProcessoTarefaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoProcessoTarefa::class);
    }

    public function findOneByProcesso(JuridicoProcesso $processo, int $id): ?JuridicoProcessoTarefa
    {
        return $this->findOneBy(['id' => $id, 'processo' => $processo]);
    }

    /** @return list<JuridicoProcessoTarefa> */
    public function findForProcesso(JuridicoProcesso $processo): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.responsavel', 'r')->addSelect('r')
            ->andWhere('t.processo = :processo')
            ->setParameter('processo', $processo)
            ->orderBy('t.status', 'ASC')
            ->addOrderBy('t.prazo', 'ASC')
            ->addOrderBy('t.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tarefas pendentes de toda a empresa (para o motor de alertas de risco), com o processo já carregado.
     *
     * @return list<JuridicoProcessoTarefa>
     */
    public function findPendentesForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.processo', 'p')->addSelect('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('t.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', JuridicoProcessoTarefa::STATUS_PENDENTE)
            ->orderBy('t.prazo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPendentesForProcesso(JuridicoProcesso $processo): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.processo = :processo')
            ->andWhere('t.status = :status')
            ->setParameter('processo', $processo)
            ->setParameter('status', JuridicoProcessoTarefa::STATUS_PENDENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
