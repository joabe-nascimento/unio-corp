<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoCliente;
use App\Entity\JuridicoProcesso;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoProcesso> */
class JuridicoProcessoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoProcesso::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoProcesso
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoProcesso> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.cliente', 'c')
            ->addSelect('c')
            ->leftJoin('p.responsavel', 'r')
            ->addSelect('r')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.criadoEm', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('p.numero LIKE :q OR c.nome LIKE :q OR p.area LIKE :q')
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

    public function countByEmpresaAndStatus(Empresa $empresa, string $status): int
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

    public function countByCliente(JuridicoCliente $cliente): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.cliente = :cliente')
            ->andWhere('p.status != :encerrado')
            ->setParameter('cliente', $cliente)
            ->setParameter('encerrado', JuridicoProcesso::STATUS_ENCERRADO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumValorByCliente(JuridicoCliente $cliente): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.valor), 0)')
            ->andWhere('p.cliente = :cliente')
            ->setParameter('cliente', $cliente)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumValorAtivoByEmpresa(Empresa $empresa): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.valor), 0)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status != :encerrado')
            ->setParameter('empresa', $empresa)
            ->setParameter('encerrado', JuridicoProcesso::STATUS_ENCERRADO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Taxa de êxito real: processos encerrados com resultado procedente/acordo sobre o total encerrado.
     */
    public function taxaExito(Empresa $empresa): ?float
    {
        $total = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.resultado IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return null;
        }

        $favoraveis = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.resultado IN (:resultados)')
            ->setParameter('empresa', $empresa)
            ->setParameter('resultados', [JuridicoProcesso::RESULTADO_PROCEDENTE, JuridicoProcesso::RESULTADO_ACORDO])
            ->getQuery()
            ->getSingleScalarResult();

        return round(($favoraveis / $total) * 100, 1);
    }

    /** @return list<JuridicoProcesso> */
    public function findAllForSelect(Empresa $empresa): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status != :encerrado')
            ->setParameter('empresa', $empresa)
            ->setParameter('encerrado', JuridicoProcesso::STATUS_ENCERRADO)
            ->orderBy('p.numero', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, array{total: int, favoraveis: int}> advogado_id => contagem de processos com resultado
     */
    public function resultadosGroupedByResponsavel(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.responsavel) AS responsavelId', 'p.resultado AS resultado', 'COUNT(p.id) AS cnt')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.resultado IS NOT NULL')
            ->andWhere('p.responsavel IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->groupBy('p.responsavel', 'p.resultado')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $id = (string) $row['responsavelId'];
            $out[$id] ??= ['total' => 0, 'favoraveis' => 0];
            $out[$id]['total'] += (int) $row['cnt'];
            if (\in_array($row['resultado'], [JuridicoProcesso::RESULTADO_PROCEDENTE, JuridicoProcesso::RESULTADO_ACORDO], true)) {
                $out[$id]['favoraveis'] += (int) $row['cnt'];
            }
        }

        return $out;
    }

    /**
     * @return array<string, int> advogado_id => processos ativos
     */
    public function countAtivosGroupedByResponsavel(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.responsavel) AS responsavelId', 'COUNT(p.id) AS cnt')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status != :encerrado')
            ->andWhere('p.responsavel IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->setParameter('encerrado', JuridicoProcesso::STATUS_ENCERRADO)
            ->groupBy('p.responsavel')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['responsavelId']] = (int) $row['cnt'];
        }

        return $out;
    }
}
