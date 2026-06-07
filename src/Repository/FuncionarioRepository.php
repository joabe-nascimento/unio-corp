<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Funcionario>
 */
class FuncionarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Funcionario::class);
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsByEmail(Empresa $empresa, string $email, ?int $excludeId = null): bool
    {
        $normalized = mb_strtolower(trim($email));
        if ($normalized === '') {
            return false;
        }

        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('LOWER(f.email) = :email')
            ->setParameter('empresa', $empresa)
            ->setParameter('email', $normalized);

        if ($excludeId !== null) {
            $qb->andWhere('f.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function existsByCpf(Empresa $empresa, string $cpf, ?int $excludeId = null): bool
    {
        $digits = preg_replace('/\D+/', '', $cpf);
        if ($digits === '') {
            return false;
        }

        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.cpf = :cpf')
            ->setParameter('empresa', $empresa)
            ->setParameter('cpf', $digits);

        if ($excludeId !== null) {
            $qb->andWhere('f.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return list<Funcionario> */
    public function findGestoresForSelect(Empresa $empresa, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', ['ATIVO', 'FERIAS', 'AFASTADO'])
            ->orderBy('f.nome', 'ASC');

        if ($excludeId !== null) {
            $qb->andWhere('f.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<Funcionario> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        return $this->findForEmpresaFiltered($empresa, $status, $q, null, null);
    }

    /** @return list<Funcionario> */
    public function findForEmpresaFiltered(
        Empresa $empresa,
        ?string $status = null,
        ?string $q = null,
        ?int $departamentoId = null,
        ?string $cargo = null,
    ): array {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.departamento', 'd')
            ->addSelect('d')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('f.nome', 'ASC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('f.status = :status')->setParameter('status', $status);
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('f.nome LIKE :q OR f.email LIKE :q OR f.cargo LIKE :q OR d.nome LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        if ($departamentoId !== null && $departamentoId > 0) {
            $qb->andWhere('f.departamento = :dept')->setParameter('dept', $departamentoId);
        }

        if ($cargo !== null && trim($cargo) !== '') {
            $qb->andWhere('f.cargo = :cargo')->setParameter('cargo', trim($cargo));
        }

        return $qb->getQuery()->getResult();
    }

    public function countWithoutDepartamento(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.departamento IS NULL')
            ->andWhere('f.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', ['ATIVO', 'FERIAS', 'AFASTADO'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<string> */
    public function findDistinctCargos(Empresa $empresa): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('DISTINCT f.cargo AS cargo')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.cargo IS NOT NULL')
            ->andWhere('f.cargo != :empty')
            ->setParameter('empresa', $empresa)
            ->setParameter('empty', '')
            ->orderBy('f.cargo', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['cargo'] ?? ''),
            $rows
        )));
    }

    /** @return list<Funcionario> */
    public function findByDepartamento(Empresa $empresa, int $departamentoId): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.departamento = :dept')
            ->setParameter('empresa', $empresa)
            ->setParameter('dept', $departamentoId)
            ->orderBy('f.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByEmpresaAndStatus(Empresa $empresa, string $status): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int> status => quantidade
     */
    public function countByStatusGrouped(Empresa $empresa): array
    {
        $defaults = ['ATIVO' => 0, 'INATIVO' => 0, 'FERIAS' => 0, 'AFASTADO' => 0];
        $rows = $this->createQueryBuilder('f')
            ->select('f.status AS status', 'COUNT(f.id) AS cnt')
            ->andWhere('f.empresa = :empresa')
            ->groupBy('f.status')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status !== '') {
                $defaults[$status] = (int) ($row['cnt'] ?? 0);
            }
        }

        return $defaults;
    }

    /** @return list<Funcionario> */
    public function findRecentByEmpresa(Empresa $empresa, int $limit = 5): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('f.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActiveWithoutSalary(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.status = :status')
            ->andWhere('f.salario IS NULL OR f.salario <= 0')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', 'ATIVO')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWithPlatformUser(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.user IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAdmittedSince(Empresa $empresa, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.dataAdmissao >= :since')
            ->setParameter('empresa', $empresa)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByUser(Empresa $empresa, User $user): ?Funcionario
    {
        return $this->findOneBy(['empresa' => $empresa, 'user' => $user]);
    }

    public function findOneByEmail(Empresa $empresa, string $email): ?Funcionario
    {
        $normalized = mb_strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        return $this->createQueryBuilder('f')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('LOWER(f.email) = :email')
            ->setParameter('empresa', $empresa)
            ->setParameter('email', $normalized)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Funcionario> */
    public function findAtivosForOrganograma(Empresa $empresa): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', ['ATIVO', 'FERIAS', 'AFASTADO'])
            ->orderBy('f.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countWithGestor(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.gestor IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
