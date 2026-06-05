<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\TiChamado;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TiChamado> */
class TiChamadoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TiChamado::class);
    }

    /** @return list<TiChamado> */
    public function findByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.abertoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCodigo(Empresa $empresa, string $codigo): ?TiChamado
    {
        return $this->findOneBy(['empresa' => $empresa, 'codigo' => $codigo]);
    }

    public function countOpen(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.status != :resolvido')
            ->setParameter('empresa', $empresa)
            ->setParameter('resolvido', TiChamado::STATUS_RESOLVIDO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(Empresa $empresa, string $status): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function nextCodigoNumber(Empresa $empresa): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('c.codigo')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getScalarResult();

        $max = 0;
        foreach ($result as $row) {
            $codigo = (string) ($row['codigo'] ?? '');
            if (preg_match('/TK-(\d+)/', $codigo, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }

    /** @return list<TiChamado> */
    public function findResolvedSince(Empresa $empresa, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.status = :resolvido')
            ->andWhere('c.resolvidoEm >= :since')
            ->setParameter('empresa', $empresa)
            ->setParameter('resolvido', TiChamado::STATUS_RESOLVIDO)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    /** @return list<array{month: string, opened: int, resolved: int}> */
    public function volumeByMonth(Empresa $empresa, int $months = 6): array
    {
        $since = (new \DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');
        $labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        /** @var list<TiChamado> $all */
        $all = $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.abertoEm >= :since')
            ->setParameter('empresa', $empresa)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $start = $since;
        $buckets = [];
        for ($i = 0; $i < $months; ++$i) {
            $monthDate = $start->modify('+' . $i . ' months');
            $key = $monthDate->format('Y-m');
            $buckets[$key] = [
                'month' => $labels[(int) $monthDate->format('n') - 1],
                'opened' => 0,
                'resolved' => 0,
            ];
        }

        foreach ($all as $chamado) {
            $key = $chamado->getAbertoEm()->format('Y-m');
            if (isset($buckets[$key])) {
                ++$buckets[$key]['opened'];
            }
            if ($chamado->getResolvidoEm() !== null) {
                $rKey = $chamado->getResolvidoEm()->format('Y-m');
                if (isset($buckets[$rKey])) {
                    ++$buckets[$rKey]['resolved'];
                }
            }
        }

        return array_values($buckets);
    }

    public function countWithHeliaTriagem(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.heliaConfianca IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOpenByResponsavel(Empresa $empresa, User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.responsavel = :user')
            ->andWhere('c.status != :resolvido')
            ->setParameter('empresa', $empresa)
            ->setParameter('user', $user)
            ->setParameter('resolvido', TiChamado::STATUS_RESOLVIDO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<TiChamado> */
    public function findBySolicitante(Empresa $empresa, User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.solicitante = :user')
            ->setParameter('empresa', $empresa)
            ->setParameter('user', $user)
            ->orderBy('c.abertoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<TiChamado> */
    public function findByAtivo(Empresa $empresa, int $ativoId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.ativo = :ativo')
            ->setParameter('empresa', $empresa)
            ->setParameter('ativo', $ativoId)
            ->orderBy('c.abertoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{correct: int, incorrect: int, total: int} */
    public function heliaFeedbackStats(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.heliaFeedback AS feedback')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.heliaFeedback IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getScalarResult();

        $correct = 0;
        $incorrect = 0;
        foreach ($rows as $row) {
            if (($row['feedback'] ?? '') === 'correct') {
                ++$correct;
            } elseif (($row['feedback'] ?? '') === 'incorrect') {
                ++$incorrect;
            }
        }

        return ['correct' => $correct, 'incorrect' => $incorrect, 'total' => $correct + $incorrect];
    }
}
