<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PosOperatorioPaciente> */
class PosOperatorioPacienteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PosOperatorioPaciente::class);
    }

    public function countAtivosByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                PosOperatorioPaciente::STATUS_ATIVO,
                PosOperatorioPaciente::STATUS_ALERTA,
                PosOperatorioPaciente::STATUS_PENDENTE,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(Empresa $empresa, string $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<PosOperatorioPaciente> */
    public function findRecentByEmpresa(Empresa $empresa, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status != :encerrado')
            ->setParameter('empresa', $empresa)
            ->setParameter('encerrado', PosOperatorioPaciente::STATUS_ENCERRADO)
            ->orderBy('p.criadoEm', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countRecentByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status != :encerrado')
            ->setParameter('empresa', $empresa)
            ->setParameter('encerrado', PosOperatorioPaciente::STATUS_ENCERRADO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByCodigo(Empresa $empresa, string $codigo): ?PosOperatorioPaciente
    {
        return $this->findOneBy(['empresa' => $empresa, 'codigo' => $codigo]);
    }

    /** @return list<PosOperatorioPaciente> */
    public function findActiveWithoutQuestionarioToday(Empresa $empresa, \DateTimeImmutable $today): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin(
                'p.questionarios',
                'q',
                'WITH',
                'q.dataReferencia = :today',
            )
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->andWhere('q.id IS NULL')
            ->setParameter('empresa', $empresa)
            ->setParameter('today', $today)
            ->setParameter('statuses', [
                PosOperatorioPaciente::STATUS_ATIVO,
                PosOperatorioPaciente::STATUS_ALERTA,
                PosOperatorioPaciente::STATUS_PENDENTE,
            ])
            ->getQuery()
            ->getResult();
    }

    public function findMaxCodigoSequence(Empresa $empresa): int
    {
        /** @var list<string> $codigos */
        $codigos = $this->createQueryBuilder('p')
            ->select('p.codigo')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.codigo LIKE :prefix')
            ->setParameter('empresa', $empresa)
            ->setParameter('prefix', 'PO-%')
            ->getQuery()
            ->getSingleColumnResult();

        $max = 1000;
        foreach ($codigos as $codigo) {
            if (preg_match('/^PO-(\d+)$/', $codigo, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }
}
