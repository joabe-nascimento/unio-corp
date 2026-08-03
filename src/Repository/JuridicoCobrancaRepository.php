<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoCobranca;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoCobranca> */
class JuridicoCobrancaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoCobranca::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoCobranca
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoCobranca> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.cliente', 'cli')
            ->addSelect('cli')
            ->leftJoin('c.processo', 'p')
            ->addSelect('p')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.vencimento', 'ASC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('c.descricao LIKE :q OR cli.nome LIKE :q OR p.numero LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** @param list<Empresa> $empresas */
    public function sumAberto(array $empresas): float
    {
        return (float) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.valor), 0)')
            ->andWhere('c.empresa IN (:empresas)')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('empresas', $empresas)
            ->setParameter('statuses', [JuridicoCobranca::STATUS_PENDENTE, JuridicoCobranca::STATUS_VENCIDO])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param list<Empresa> $empresas */
    public function sumRecebidoMes(array $empresas, ?string $mes = null): float
    {
        $mes ??= (new \DateTimeImmutable('today'))->format('Y-m');
        [$ano, $mesNum] = explode('-', $mes);
        $inicio = new \DateTimeImmutable("{$ano}-{$mesNum}-01");
        $fim = $inicio->modify('first day of next month');

        return (float) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.valor), 0)')
            ->andWhere('c.empresa IN (:empresas)')
            ->andWhere('c.status = :pago')
            ->andWhere('c.pagoEm >= :inicio AND c.pagoEm < :fim')
            ->setParameter('empresas', $empresas)
            ->setParameter('pago', JuridicoCobranca::STATUS_PAGO)
            ->setParameter('inicio', $inicio)
            ->setParameter('fim', $fim)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param list<Empresa> $empresas */
    public function countVencidas(array $empresas): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa IN (:empresas)')
            ->andWhere('c.status = :vencido')
            ->setParameter('empresas', $empresas)
            ->setParameter('vencido', JuridicoCobranca::STATUS_VENCIDO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Aging de inadimplência: faixas 0-30, 31-60, 61-90, 90+ dias.
     *
     * @param list<Empresa> $empresas
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function agingGrupo(array $empresas): array
    {
        $hoje = new \DateTimeImmutable('today');
        $faixas = [
            '0-30 dias' => [0, 30],
            '31-60 dias' => [31, 60],
            '61-90 dias' => [61, 90],
            '90+ dias' => [91, 9999],
        ];
        $labels = [];
        $values = [];

        foreach ($faixas as $label => [$min, $max]) {
            $labels[] = $label;
            $qb = $this->createQueryBuilder('c')
                ->select('COALESCE(SUM(c.valor), 0)')
                ->andWhere('c.empresa IN (:empresas)')
                ->andWhere('c.status = :vencido')
                ->setParameter('empresas', $empresas)
                ->setParameter('vencido', JuridicoCobranca::STATUS_VENCIDO);

            if ($min === 0) {
                $limiteMax = $hoje->modify('-' . $min . ' days');
                $limiteMin = $hoje->modify('-' . $max . ' days');
                $qb->andWhere('c.vencimento <= :limiteMax AND c.vencimento >= :limiteMin')
                    ->setParameter('limiteMax', $limiteMax)
                    ->setParameter('limiteMin', $limiteMin);
            } elseif ($max >= 9999) {
                $limiteMin = $hoje->modify('-' . $min . ' days');
                $qb->andWhere('c.vencimento < :limiteMin')
                    ->setParameter('limiteMin', $limiteMin);
            } else {
                $limiteMax = $hoje->modify('-' . $min . ' days');
                $limiteMin = $hoje->modify('-' . $max . ' days');
                $qb->andWhere('c.vencimento <= :limiteMax AND c.vencimento >= :limiteMin')
                    ->setParameter('limiteMax', $limiteMax)
                    ->setParameter('limiteMin', $limiteMin);
            }

            $values[] = (float) $qb->getQuery()->getSingleScalarResult();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param list<Empresa> $empresas
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function recebidoUltimosMeses(array $empresas, int $meses = 6): array
    {
        $tz = new \DateTimeZone('America/Sao_Paulo');
        $now = new \DateTimeImmutable('now', $tz);
        $mesesPt = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $labels = [];
        $values = [];

        for ($i = $meses - 1; $i >= 0; --$i) {
            $inicio = $now->modify('first day of this month')->modify("-{$i} months")->setTime(0, 0);
            $fim = $inicio->modify('last day of this month')->setTime(23, 59, 59);
            $labels[] = $mesesPt[(int) $inicio->format('n') - 1] . '/' . $inicio->format('y');

            $values[] = (float) $this->createQueryBuilder('c')
                ->select('COALESCE(SUM(c.valor), 0)')
                ->andWhere('c.empresa IN (:empresas)')
                ->andWhere('c.status = :pago')
                ->andWhere('c.pagoEm >= :inicio AND c.pagoEm < :fim')
                ->setParameter('empresas', $empresas)
                ->setParameter('pago', JuridicoCobranca::STATUS_PAGO)
                ->setParameter('inicio', $inicio)
                ->setParameter('fim', $fim->modify('+1 day'))
                ->getQuery()
                ->getSingleScalarResult();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Marca títulos pendentes com vencimento passado como vencidos. */
    public function atualizarVencidos(Empresa $empresa): int
    {
        $hoje = new \DateTimeImmutable('today');

        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.status', ':vencido')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.status = :pendente')
            ->andWhere('c.vencimento < :hoje')
            ->setParameter('vencido', JuridicoCobranca::STATUS_VENCIDO)
            ->setParameter('empresa', $empresa)
            ->setParameter('pendente', JuridicoCobranca::STATUS_PENDENTE)
            ->setParameter('hoje', $hoje)
            ->getQuery()
            ->execute();
    }
}
