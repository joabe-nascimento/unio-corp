<?php

namespace App\Repository\Organismo;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoMemoryFact;
use App\Entity\PosOperatorioPaciente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrganismoMemoryFact> */
class OrganismoMemoryFactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganismoMemoryFact::class);
    }

    /** @return list<OrganismoMemoryFact> */
    public function findRecent(Empresa $empresa, int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('f.peso', 'DESC')
            ->addOrderBy('f.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<OrganismoMemoryFact> */
    public function findForPaciente(PosOperatorioPaciente $paciente, int $limit = 8): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.paciente = :paciente')
            ->setParameter('paciente', $paciente)
            ->orderBy('f.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<array{tipo: string, total: int}> */
    public function topPatterns(Empresa $empresa, int $limit = 5): array
    {
        /** @var list<array{tipo: string, total: string}> $rows */
        $rows = $this->createQueryBuilder('f')
            ->select('f.tipo AS tipo, COUNT(f.id) AS total')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.criadoEm >= :since')
            ->setParameter('empresa', $empresa)
            ->setParameter('since', new \DateTimeImmutable('-30 days'))
            ->groupBy('f.tipo')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): array => [
            'tipo' => (string) $r['tipo'],
            'total' => (int) $r['total'],
        ], $rows);
    }
}
