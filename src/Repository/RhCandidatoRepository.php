<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\RhCandidato;
use App\Entity\RhVaga;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhCandidatoOrigem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RhCandidato>
 */
class RhCandidatoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhCandidato::class);
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Candidatos em etapas ativas (exclui reprovados). */
    public function countAtivosByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('c.etapa != :reprovado')
            ->setParameter('empresa', $empresa)
            ->setParameter('reprovado', RhCandidatoEtapa::REPROVADO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForEmpresa(int $id, Empresa $empresa): ?RhCandidato
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('c.id = :id')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('id', $id)
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<RhCandidato> */
    public function findForEmpresa(Empresa $empresa, ?int $vagaId = null, ?string $q = null, ?string $etapa = null, ?string $origem = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.vaga', 'v')
            ->addSelect('v')
            ->leftJoin('c.onboardingProcess', 'o')
            ->addSelect('o')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.criadoEm', 'DESC');

        if ($vagaId !== null && $vagaId > 0) {
            $qb->andWhere('v.id = :vagaId')->setParameter('vagaId', $vagaId);
        }

        $q = $q !== null ? trim($q) : '';
        if ($q !== '') {
            $qb->andWhere(
                'LOWER(c.nome) LIKE :q OR LOWER(c.email) LIKE :q OR LOWER(c.telefone) LIKE :q OR LOWER(v.titulo) LIKE :q',
            )->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        $etapa = $etapa !== null ? trim($etapa) : '';
        if ($etapa !== '' && RhCandidatoEtapa::isValid($etapa)) {
            $qb->andWhere('c.etapa = :etapa')->setParameter('etapa', $etapa);
        }

        $origem = $origem !== null ? trim($origem) : '';
        if ($origem !== '' && RhCandidatoOrigem::isValid($origem)) {
            $qb->andWhere('c.origem = :origem')->setParameter('origem', $origem);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<RhCandidato> */
    public function findByVaga(RhVaga $vaga): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.vaga = :vaga')
            ->setParameter('vaga', $vaga)
            ->orderBy('c.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByVaga(RhVaga $vaga): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.vaga = :vaga')
            ->setParameter('vaga', $vaga)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countContratadosByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('c.etapa = :contratado')
            ->setParameter('empresa', $empresa)
            ->setParameter('contratado', RhCandidatoEtapa::CONTRATADO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByEtapaForEmpresa(Empresa $empresa, string $etapa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('c.etapa = :etapa')
            ->setParameter('empresa', $empresa)
            ->setParameter('etapa', $etapa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Matriz origem × etapa para gráficos Sankey.
     *
     * @return list<array{origem: string, etapa: string, total: int}>
     */
    public function countOrigemEtapaForEmpresa(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.origem AS origem, c.etapa AS etapa, COUNT(c.id) AS total')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('c.origem, c.etapa')
            ->having('COUNT(c.id) > 0')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $origem = (string) ($row['origem'] ?? '');
            $etapa = (string) ($row['etapa'] ?? '');
            if (!RhCandidatoOrigem::isValid($origem) || !RhCandidatoEtapa::isValid($etapa)) {
                continue;
            }
            $out[] = [
                'origem' => $origem,
                'etapa' => $etapa,
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $out;
    }

    /** Média de dias entre cadastro do candidato e criação da admissão. */
    public function avgTimeToHireDays(Empresa $empresa): ?int
    {
        $candidatos = $this->createQueryBuilder('c')
            ->innerJoin('c.vaga', 'v')
            ->innerJoin('c.onboardingProcess', 'o')
            ->addSelect('o')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('c.etapa = :contratado')
            ->setParameter('empresa', $empresa)
            ->setParameter('contratado', RhCandidatoEtapa::CONTRATADO)
            ->getQuery()
            ->getResult();

        if ($candidatos === []) {
            return null;
        }

        $totalDays = 0;
        foreach ($candidatos as $candidato) {
            $process = $candidato->getOnboardingProcess();
            if ($process === null) {
                continue;
            }
            $totalDays += max(0, $process->getCriadoEm()->diff($candidato->getCriadoEm())->days);
        }

        return (int) round($totalDays / \count($candidatos));
    }

    /** @return array<int, int> */
    public function countGroupedByVagaForEmpresa(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('v.id AS vagaId, COUNT(c.id) AS total')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('v.id')
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['vagaId']] = (int) $row['total'];
        }

        return $map;
    }

    /** @return array<int, list<RhCandidato>> */
    public function findGroupedByVagaForEmpresa(Empresa $empresa): array
    {
        $grouped = [];
        foreach ($this->findForEmpresa($empresa) as $candidato) {
            $vagaId = $candidato->getVaga()->getId();
            if ($vagaId === null) {
                continue;
            }
            $grouped[$vagaId][] = $candidato;
        }

        return $grouped;
    }

    public function existsByEmailAndVaga(string $email, RhVaga $vaga, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.vaga = :vaga')
            ->andWhere('LOWER(c.email) = :email')
            ->setParameter('vaga', $vaga)
            ->setParameter('email', mb_strtolower(trim($email)));

        if ($excludeId !== null && $excludeId > 0) {
            $qb->andWhere('c.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return array<string, int> */
    public function countByOrigemForEmpresa(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.origem AS origem, COUNT(c.id) AS total')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('v.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('c.origem')
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach (RhCandidatoOrigem::ALL as $origem) {
            $map[$origem] = 0;
        }
        foreach ($rows as $row) {
            $origem = (string) $row['origem'];
            if (RhCandidatoOrigem::isValid($origem)) {
                $map[$origem] = (int) $row['total'];
            }
        }

        return $map;
    }

    public function hireRatePercent(Empresa $empresa): ?float
    {
        $total = $this->countByEmpresa($empresa);
        if ($total === 0) {
            return null;
        }

        $contratados = $this->countContratadosByEmpresa($empresa);

        return round($contratados / $total * 100, 1);
    }

    /** @return list<RhCandidato> */
    public function findProximasEntrevistas(Empresa $empresa, int $limit = 5): array
    {
        $now = new \DateTimeImmutable();
        $ate = $now->modify('+14 days');

        return $this->createQueryBuilder('c')
            ->innerJoin('c.vaga', 'v')
            ->addSelect('v')
            ->leftJoin('c.entrevistaEntrevistador', 'ent')
            ->addSelect('ent')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('c.entrevistaEm IS NOT NULL')
            ->andWhere('c.entrevistaEm >= :now')
            ->andWhere('c.entrevistaEm <= :ate')
            ->andWhere('c.etapa != :reprovado')
            ->setParameter('empresa', $empresa)
            ->setParameter('now', $now)
            ->setParameter('ate', $ate)
            ->setParameter('reprovado', RhCandidatoEtapa::REPROVADO)
            ->orderBy('c.entrevistaEm', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function countProximasEntrevistas(Empresa $empresa): int
    {
        $now = new \DateTimeImmutable();
        $ate = $now->modify('+14 days');

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.vaga', 'v')
            ->andWhere('v.empresa = :empresa')
            ->andWhere('c.entrevistaEm IS NOT NULL')
            ->andWhere('c.entrevistaEm >= :now')
            ->andWhere('c.entrevistaEm <= :ate')
            ->andWhere('c.etapa != :reprovado')
            ->setParameter('empresa', $empresa)
            ->setParameter('now', $now)
            ->setParameter('ate', $ate)
            ->setParameter('reprovado', RhCandidatoEtapa::REPROVADO)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
