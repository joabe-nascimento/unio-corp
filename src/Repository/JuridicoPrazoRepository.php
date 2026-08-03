<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoPrazo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoPrazo> */
class JuridicoPrazoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoPrazo::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoPrazo
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoPrazo> */
    public function findForEmpresa(Empresa $empresa, ?string $situacao = null, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.processo', 'proc')
            ->addSelect('proc')
            ->leftJoin('p.responsavel', 'r')
            ->addSelect('r')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.dataLimite', 'ASC');

        if ($situacao === 'pendentes') {
            $qb->andWhere('p.cumprido = false');
        } elseif ($situacao === 'cumpridos') {
            $qb->andWhere('p.cumprido = true');
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('p.tipo LIKE :q OR p.descricao LIKE :q OR proc.numero LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countCriticosByEmpresa(Empresa $empresa): int
    {
        $limite = (new \DateTimeImmutable('today'))->modify('+' . JuridicoPrazo::LIMIAR_CRITICO_DIAS . ' days');

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.cumprido = false')
            ->andWhere('p.dataLimite <= :limite')
            ->setParameter('empresa', $empresa)
            ->setParameter('limite', $limite)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPendentes(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.cumprido = false')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countVencemHoje(Empresa $empresa): int
    {
        $hoje = new \DateTimeImmutable('today');
        $amanha = $hoje->modify('+1 day');

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.cumprido = false')
            ->andWhere('p.dataLimite >= :hoje')
            ->andWhere('p.dataLimite < :amanha')
            ->setParameter('empresa', $empresa)
            ->setParameter('hoje', $hoje)
            ->setParameter('amanha', $amanha)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Percentual de prazos cumpridos dentro do prazo (SLA) — usado no BI de carteira.
     *
     * @param list<Empresa> $empresas
     *
     * @return array{cumpridos: int, vencidos_pendentes: int, no_prazo: int, taxa: float}
     */
    public function slaGrupo(array $empresas): array
    {
        $hoje = new \DateTimeImmutable('today');

        $cumpridos = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa IN (:empresas)')
            ->andWhere('p.cumprido = true')
            ->setParameter('empresas', $empresas)
            ->getQuery()
            ->getSingleScalarResult();

        $vencidosPendentes = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa IN (:empresas)')
            ->andWhere('p.cumprido = false')
            ->andWhere('p.dataLimite < :hoje')
            ->setParameter('empresas', $empresas)
            ->setParameter('hoje', $hoje)
            ->getQuery()
            ->getSingleScalarResult();

        $noPrazo = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa IN (:empresas)')
            ->andWhere('p.cumprido = false')
            ->andWhere('p.dataLimite >= :hoje')
            ->setParameter('empresas', $empresas)
            ->setParameter('hoje', $hoje)
            ->getQuery()
            ->getSingleScalarResult();

        $totalDecidido = $cumpridos + $vencidosPendentes;
        $taxa = $totalDecidido > 0 ? round(($cumpridos / $totalDecidido) * 100, 1) : 100.0;

        return [
            'cumpridos' => $cumpridos,
            'vencidos_pendentes' => $vencidosPendentes,
            'no_prazo' => $noPrazo,
            'taxa' => $taxa,
        ];
    }
}
