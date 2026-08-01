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

    public function findByNumeroNorm(Empresa $empresa, string $numeroNorm): ?JuridicoProcesso
    {
        $numeroNorm = preg_replace('/\D+/', '', $numeroNorm) ?? '';
        if ($numeroNorm === '') {
            return null;
        }

        $processos = $this->findForEmpresa($empresa);
        foreach ($processos as $processo) {
            $digits = preg_replace('/\D+/', '', $processo->getNumero()) ?? '';
            if ($digits === $numeroNorm) {
                return $processo;
            }
        }

        return null;
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

    /** @param list<Empresa> $empresas */
    public function sumValorAtivoByEmpresas(array $empresas): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.valor), 0)')
            ->andWhere('p.empresa IN (:empresas)')
            ->andWhere('p.status != :encerrado')
            ->setParameter('empresas', $empresas)
            ->setParameter('encerrado', JuridicoProcesso::STATUS_ENCERRADO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param list<Empresa> $empresas */
    public function countByEmpresasAndStatus(array $empresas, string $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa IN (:empresas)')
            ->andWhere('p.status = :status')
            ->setParameter('empresas', $empresas)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param list<Empresa> $empresas */
    public function taxaExitoGrupo(array $empresas): ?float
    {
        $total = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa IN (:empresas)')
            ->andWhere('p.resultado IS NOT NULL')
            ->setParameter('empresas', $empresas)
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return null;
        }

        $favoraveis = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa IN (:empresas)')
            ->andWhere('p.resultado IN (:resultados)')
            ->setParameter('empresas', $empresas)
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
     * @param list<Empresa> $empresas
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function countByAreaGrouped(array $empresas): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select("COALESCE(NULLIF(p.area, ''), 'Não informada') AS area", 'COUNT(p.id) AS total')
            ->andWhere('p.empresa IN (:empresas)')
            ->setParameter('empresas', $empresas)
            ->groupBy('area')
            ->orderBy('total', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        return $this->pluckLabelValue($rows, 'area');
    }

    /**
     * @param list<Empresa> $empresas
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function countByFaseGrouped(array $empresas): array
    {
        $labelsMap = [
            JuridicoProcesso::FASE_CONHECIMENTO => 'Conhecimento',
            JuridicoProcesso::FASE_INSTRUCAO => 'Instrução',
            JuridicoProcesso::FASE_SENTENCA => 'Sentença',
            JuridicoProcesso::FASE_RECURSAL => 'Recursal',
            JuridicoProcesso::FASE_EXECUCAO => 'Execução',
            JuridicoProcesso::FASE_ENCERRADO => 'Encerrado',
        ];

        $rows = $this->createQueryBuilder('p')
            ->select('p.fase AS fase', 'COUNT(p.id) AS total')
            ->andWhere('p.empresa IN (:empresas)')
            ->setParameter('empresas', $empresas)
            ->groupBy('p.fase')
            ->getQuery()
            ->getArrayResult();

        $labels = [];
        $values = [];
        foreach ($labelsMap as $key => $label) {
            $found = null;
            foreach ($rows as $row) {
                if ($row['fase'] === $key) {
                    $found = (int) $row['total'];
                    break;
                }
            }
            if ($found === null) {
                continue;
            }
            $labels[] = $label;
            $values[] = $found;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param list<Empresa> $empresas
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function countByStatusGrouped(array $empresas): array
    {
        $map = [
            JuridicoProcesso::STATUS_ATIVO => 'Ativos',
            JuridicoProcesso::STATUS_CRITICO => 'Críticos',
            JuridicoProcesso::STATUS_ENCERRADO => 'Encerrados',
        ];

        $rows = $this->createQueryBuilder('p')
            ->select('p.status AS status', 'COUNT(p.id) AS total')
            ->andWhere('p.empresa IN (:empresas)')
            ->setParameter('empresas', $empresas)
            ->groupBy('p.status')
            ->getQuery()
            ->getArrayResult();

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = $map[$row['status']] ?? (string) $row['status'];
            $values[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param list<Empresa> $empresas
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function countByTribunalGrouped(array $empresas): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select("COALESCE(NULLIF(p.tribunal, ''), 'Não informado') AS tribunal", 'COUNT(p.id) AS total')
            ->andWhere('p.empresa IN (:empresas)')
            ->setParameter('empresas', $empresas)
            ->groupBy('tribunal')
            ->orderBy('total', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getArrayResult();

        return $this->pluckLabelValue($rows, 'tribunal');
    }

    /**
     * Evolução mensal de novos processos cadastrados (últimos $meses).
     *
     * @param list<Empresa> $empresas
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function evolucaoMensal(array $empresas, int $meses = 6): array
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

            $values[] = (int) $this->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.empresa IN (:empresas)')
                ->andWhere('p.criadoEm BETWEEN :inicio AND :fim')
                ->setParameter('empresas', $empresas)
                ->setParameter('inicio', $inicio)
                ->setParameter('fim', $fim)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Taxa de êxito histórica por área do direito — usada pela previsão de êxito heurística.
     *
     * @return array<string, array{total: int, favoraveis: int, taxa: float}>
     */
    public function taxaExitoPorArea(Empresa $empresa): array
    {
        return $this->taxaExitoPorAreaGrupo([$empresa]);
    }

    /**
     * Igual a {@see self::taxaExitoPorArea()}, mas consolidando o grupo econômico
     * (matriz + filiais) — usado para calibrar o modelo treinado com mais amostras
     * quando o escritório faz parte de uma rede.
     *
     * @param list<Empresa> $empresas
     *
     * @return array<string, array{total: int, favoraveis: int, taxa: float}>
     */
    public function taxaExitoPorAreaGrupo(array $empresas): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select("COALESCE(NULLIF(p.area, ''), 'geral') AS area", 'p.resultado AS resultado', 'COUNT(p.id) AS cnt')
            ->andWhere('p.empresa IN (:empresas)')
            ->andWhere('p.resultado IS NOT NULL')
            ->setParameter('empresas', $empresas)
            ->groupBy('area, p.resultado')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $area = (string) $row['area'];
            $out[$area] ??= ['total' => 0, 'favoraveis' => 0, 'taxa' => 0.0];
            $out[$area]['total'] += (int) $row['cnt'];
            if (\in_array($row['resultado'], [JuridicoProcesso::RESULTADO_PROCEDENTE, JuridicoProcesso::RESULTADO_ACORDO], true)) {
                $out[$area]['favoraveis'] += (int) $row['cnt'];
            }
        }

        foreach ($out as $area => $dados) {
            $out[$area]['taxa'] = $dados['total'] > 0 ? round(($dados['favoraveis'] / $dados['total']) * 100, 1) : 0.0;
        }

        return $out;
    }

    /**
     * Processos encerrados com resultado conhecido — base de treino do modelo
     * estatístico de previsão de êxito (regressão logística calibrada por escritório
     * ou grupo econômico).
     *
     * @param list<Empresa> $empresas
     *
     * @return list<JuridicoProcesso>
     */
    public function findComResultado(array $empresas): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa IN (:empresas)')
            ->andWhere('p.resultado IS NOT NULL')
            ->setParameter('empresas', $empresas)
            ->getQuery()
            ->getResult();
    }

    /** @param list<array<string, mixed>> $rows @return array{labels: list<string>, values: list<int>} */
    private function pluckLabelValue(array $rows, string $labelField): array
    {
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = (string) $row[$labelField];
            $values[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'values' => $values];
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
