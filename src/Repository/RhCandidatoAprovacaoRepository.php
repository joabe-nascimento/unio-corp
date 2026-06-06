<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhCandidato;
use App\Entity\RhCandidatoAprovacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RhCandidatoAprovacao> */
class RhCandidatoAprovacaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhCandidatoAprovacao::class);
    }

    /** @return list<RhCandidatoAprovacao> */
    public function findPendentesForEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.candidato', 'c')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('a.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhCandidatoAprovacao::STATUS_PENDENTE)
            ->orderBy('a.criadoEm', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<RhCandidatoAprovacao> */
    public function findForCandidato(RhCandidato $candidato): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.candidato = :candidato')
            ->setParameter('candidato', $candidato)
            ->orderBy('a.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasPendenteForCandidatoEtapa(RhCandidato $candidato, string $etapaDestino): bool
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.candidato = :candidato')
            ->andWhere('a.etapaDestino = :etapa')
            ->andWhere('a.status = :status')
            ->setParameter('candidato', $candidato)
            ->setParameter('etapa', $etapaDestino)
            ->setParameter('status', RhCandidatoAprovacao::STATUS_PENDENTE)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function countPendentesForEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->innerJoin('a.candidato', 'c')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('a.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', RhCandidatoAprovacao::STATUS_PENDENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
