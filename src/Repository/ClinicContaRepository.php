<?php

namespace App\Repository;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicAtendimento;
use App\Entity\ClinicConta;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicConta> */
class ClinicContaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicConta::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicConta
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    public function findOneByAgendamento(Empresa $empresa, ClinicAgendamento $agendamento): ?ClinicConta
    {
        return $this->findOneBy(['empresa' => $empresa, 'agendamento' => $agendamento]);
    }

    public function findOneByAtendimento(Empresa $empresa, ClinicAtendimento $atendimento): ?ClinicConta
    {
        return $this->findOneBy(['empresa' => $empresa, 'atendimento' => $atendimento]);
    }

    public const LIST_LIMIT = 80;

    /**
     * @return list<ClinicConta>
     */
    public function findByEmpresaAndStatus(Empresa $empresa, ?string $status = null, int $limit = self::LIST_LIMIT): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.criadoEm', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null && $status !== '' && $status !== 'todos') {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresaAndStatus(Empresa $empresa, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa);

        if ($status !== null && $status !== '' && $status !== 'todos') {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function sumValorCentavosByStatus(Empresa $empresa, string $status): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.valorCentavos), 0)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
