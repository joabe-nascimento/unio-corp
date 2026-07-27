<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoHonorarioLancamento;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoHonorarioLancamento> */
class JuridicoHonorarioLancamentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoHonorarioLancamento::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoHonorarioLancamento
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoHonorarioLancamento> */
    public function findForEmpresa(Empresa $empresa, ?int $advogadoId = null, ?string $mes = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.advogado', 'a')
            ->addSelect('a')
            ->leftJoin('l.processo', 'p')
            ->addSelect('p')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('l.data', 'DESC');

        if ($advogadoId !== null && $advogadoId > 0) {
            $qb->andWhere('a.id = :advogadoId')->setParameter('advogadoId', $advogadoId);
        }

        if ($mes !== null && preg_match('/^\d{4}-\d{2}$/', $mes)) {
            [$ano, $mesNum] = explode('-', $mes);
            $inicio = new \DateTimeImmutable("{$ano}-{$mesNum}-01");
            $fim = $inicio->modify('first day of next month');
            $qb->andWhere('l.data >= :inicio AND l.data < :fim')
                ->setParameter('inicio', $inicio)
                ->setParameter('fim', $fim);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, array{advogado: User, horas: float, receita: float}>
     */
    public function resumoPorAdvogado(Empresa $empresa, ?string $mes = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.advogado) AS advogadoId', 'SUM(l.horas) AS horas', 'SUM(l.horas * l.valorHora) AS receita')
            ->andWhere('l.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('l.advogado');

        if ($mes !== null && preg_match('/^\d{4}-\d{2}$/', $mes)) {
            [$ano, $mesNum] = explode('-', $mes);
            $inicio = new \DateTimeImmutable("{$ano}-{$mesNum}-01");
            $fim = $inicio->modify('first day of next month');
            $qb->andWhere('l.data >= :inicio AND l.data < :fim')
                ->setParameter('inicio', $inicio)
                ->setParameter('fim', $fim);
        }

        $rows = $qb->getQuery()->getArrayResult();
        if ($rows === []) {
            return [];
        }

        $userRepo = $this->getEntityManager()->getRepository(User::class);
        $advogados = $userRepo->createQueryBuilder('u')
            ->andWhere('u.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (array $r) => (int) $r['advogadoId'], $rows))
            ->getQuery()
            ->getResult();
        $byId = [];
        foreach ($advogados as $advogado) {
            $byId[$advogado->getId()] = $advogado;
        }

        $out = [];
        foreach ($rows as $row) {
            $advogado = $byId[(int) $row['advogadoId']] ?? null;
            if (!$advogado) {
                continue;
            }
            $out[] = [
                'advogado' => $advogado,
                'horas' => (float) $row['horas'],
                'receita' => (float) $row['receita'],
            ];
        }

        return $out;
    }

    public function sumReceitaMes(Empresa $empresa, ?string $mes = null): float
    {
        $mes ??= (new \DateTimeImmutable('today'))->format('Y-m');
        [$ano, $mesNum] = explode('-', $mes);
        $inicio = new \DateTimeImmutable("{$ano}-{$mesNum}-01");
        $fim = $inicio->modify('first day of next month');

        return (float) $this->createQueryBuilder('l')
            ->select('COALESCE(SUM(l.horas * l.valorHora), 0)')
            ->andWhere('l.empresa = :empresa')
            ->andWhere('l.data >= :inicio AND l.data < :fim')
            ->setParameter('empresa', $empresa)
            ->setParameter('inicio', $inicio)
            ->setParameter('fim', $fim)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumHorasMes(Empresa $empresa, ?string $mes = null): float
    {
        $mes ??= (new \DateTimeImmutable('today'))->format('Y-m');
        [$ano, $mesNum] = explode('-', $mes);
        $inicio = new \DateTimeImmutable("{$ano}-{$mesNum}-01");
        $fim = $inicio->modify('first day of next month');

        return (float) $this->createQueryBuilder('l')
            ->select('COALESCE(SUM(l.horas), 0)')
            ->andWhere('l.empresa = :empresa')
            ->andWhere('l.data >= :inicio AND l.data < :fim')
            ->setParameter('empresa', $empresa)
            ->setParameter('inicio', $inicio)
            ->setParameter('fim', $fim)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
