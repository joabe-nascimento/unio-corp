<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PosOperatorioPaciente> */
class PosOperatorioPacienteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PosOperatorioPaciente::class);
    }

    public function countAtivosByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                PosOperatorioPaciente::STATUS_ATIVO,
                PosOperatorioPaciente::STATUS_ALERTA,
                PosOperatorioPaciente::STATUS_PENDENTE,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(Empresa $empresa, string $status): int
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

    /** @return list<PosOperatorioPaciente> */
    public function findRecentByEmpresa(Empresa $empresa, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status != :encerrado')
            ->setParameter('empresa', $empresa)
            ->setParameter('encerrado', PosOperatorioPaciente::STATUS_ENCERRADO)
            ->orderBy('p.criadoEm', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Lista operacional com busca (nome/código/procedimento) e filtro de status.
     *
     * @return list<PosOperatorioPaciente>
     */
    public function searchByEmpresa(
        Empresa $empresa,
        string $q = '',
        string $status = '',
        int $limit = 100,
        int $offset = 0,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.criadoEm', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $q = trim($q);
        if ($q !== '') {
            $qb->andWhere('p.nome LIKE :q OR p.codigo LIKE :q OR p.procedimento LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        $status = trim($status);
        if ($status !== '') {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        } else {
            $qb->andWhere('p.status != :encerrado')
                ->setParameter('encerrado', PosOperatorioPaciente::STATUS_ENCERRADO);
        }

        return $qb->getQuery()->getResult();
    }

    public function countRecentByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status != :encerrado')
            ->setParameter('empresa', $empresa)
            ->setParameter('encerrado', PosOperatorioPaciente::STATUS_ENCERRADO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOperacionalByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status != :encerrado')
            ->andWhere('p.codigo NOT LIKE :demo')
            ->setParameter('empresa', $empresa)
            ->setParameter('encerrado', PosOperatorioPaciente::STATUS_ENCERRADO)
            ->setParameter('demo', 'PO-DEMO%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByCodigo(Empresa $empresa, string $codigo): ?PosOperatorioPaciente
    {
        return $this->findOneBy(['empresa' => $empresa, 'codigo' => $codigo]);
    }

    /** @return list<PosOperatorioPaciente> */
    public function findActiveWithoutQuestionarioToday(Empresa $empresa, \DateTimeImmutable $today): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin(
                'p.questionarios',
                'q',
                'WITH',
                'q.dataReferencia = :today',
            )
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->andWhere('q.id IS NULL')
            ->setParameter('empresa', $empresa)
            ->setParameter('today', $today)
            ->setParameter('statuses', [
                PosOperatorioPaciente::STATUS_ATIVO,
                PosOperatorioPaciente::STATUS_ALERTA,
                PosOperatorioPaciente::STATUS_PENDENTE,
            ])
            ->getQuery()
            ->getResult();
    }

    public function findMaxCodigoSequence(Empresa $empresa): int
    {
        /** @var list<string> $codigos */
        $codigos = $this->createQueryBuilder('p')
            ->select('p.codigo')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.codigo LIKE :prefix')
            ->setParameter('empresa', $empresa)
            ->setParameter('prefix', 'PO-%')
            ->getQuery()
            ->getSingleColumnResult();

        $max = 1000;
        foreach ($codigos as $codigo) {
            if (preg_match('/^PO-(\d+)$/', $codigo, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    /** @return list<PosOperatorioPaciente> */
    public function findComCarteirinha(Empresa $empresa): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.carteirinhaVerificacao IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.carteirinhaEmitidaEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCarteirinhaVerificacao(Empresa $empresa, string $codigo): ?PosOperatorioPaciente
    {
        return $this->findOneBy([
            'empresa' => $empresa,
            'carteirinhaVerificacao' => strtoupper(trim($codigo)),
        ]);
    }

    public function findByCodigoGlobal(string $codigo): ?PosOperatorioPaciente
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.codigo = :codigo')
            ->setParameter('codigo', $codigo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByVerificacaoGlobal(string $codigo): ?PosOperatorioPaciente
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.carteirinhaVerificacao = :codigo')
            ->setParameter('codigo', $codigo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByComprovanteVerificacaoGlobal(string $codigo): ?PosOperatorioPaciente
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.comprovanteVerificacao = :codigo')
            ->setParameter('codigo', $codigo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByAnyVerificacaoGlobal(string $codigo): ?PosOperatorioPaciente
    {
        return $this->findByVerificacaoGlobal($codigo) ?? $this->findByComprovanteVerificacaoGlobal($codigo);
    }

    /**
     * @return array{tipo: string, paciente: PosOperatorioPaciente}|null
     */
    public function resolveVerificacaoGlobal(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        $carteirinha = $this->findByVerificacaoGlobal($codigo);
        if ($carteirinha instanceof PosOperatorioPaciente) {
            return ['tipo' => 'carteirinha', 'paciente' => $carteirinha];
        }

        $comprovante = $this->findByComprovanteVerificacaoGlobal($codigo);
        if ($comprovante instanceof PosOperatorioPaciente) {
            return ['tipo' => 'comprovante', 'paciente' => $comprovante];
        }

        return null;
    }

    /** @return list<PosOperatorioPaciente> */
    public function findComComprovante(Empresa $empresa): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.comprovanteVerificacao IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.comprovanteEmitidaEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCpfGlobal(string $cpf): ?PosOperatorioPaciente
    {
        $cpf = preg_replace('/\D+/', '', $cpf) ?? '';
        if ($cpf === '' || strlen($cpf) !== 11) {
            return null;
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.cpf = :cpf')
            ->setParameter('cpf', $cpf)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByCpf(Empresa $empresa, string $cpf, ?int $excludeId = null): bool
    {
        $cpf = preg_replace('/\D+/', '', $cpf) ?? '';
        if ($cpf === '' || strlen($cpf) !== 11) {
            return false;
        }

        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.cpf = :cpf')
            ->setParameter('empresa', $empresa)
            ->setParameter('cpf', $cpf);

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function countActiveByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.status IN (:statuses)')
            ->andWhere('p.codigo NOT LIKE :demo')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', [
                PosOperatorioPaciente::STATUS_ATIVO,
                PosOperatorioPaciente::STATUS_ALERTA,
                PosOperatorioPaciente::STATUS_PENDENTE,
            ])
            ->setParameter('demo', 'PO-DEMO%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByCpfAndEmpresa(Empresa $empresa, string $cpf): ?PosOperatorioPaciente
    {
        $cpf = preg_replace('/\D+/', '', $cpf) ?? '';
        if ($cpf === '' || strlen($cpf) !== 11) {
            return null;
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.cpf = :cpf')
            ->setParameter('empresa', $empresa)
            ->setParameter('cpf', $cpf)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<PosOperatorioPaciente> */
    public function findDependentesByTitularCpf(Empresa $empresa, string $titularCpf): array
    {
        $titularCpf = preg_replace('/\D+/', '', $titularCpf) ?? '';
        if ($titularCpf === '') {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.titularCpf = :cpf OR (p.titularCpf IS NULL AND p.cpf = :cpf)')
            ->setParameter('empresa', $empresa)
            ->setParameter('cpf', $titularCpf)
            ->orderBy('p.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<PosOperatorioPaciente> */
    public function findCarteirinhasExpirando(Empresa $empresa, int $dias = 7): array
    {
        $hoje = new \DateTimeImmutable('today');
        $limite = $hoje->modify(sprintf('+%d days', max(1, $dias)));

        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.carteirinhaVerificacao IS NOT NULL')
            ->andWhere('p.carteirinhaValidaAte IS NOT NULL')
            ->andWhere('p.carteirinhaValidaAte BETWEEN :hoje AND :limite')
            ->setParameter('empresa', $empresa)
            ->setParameter('hoje', $hoje)
            ->setParameter('limite', $limite)
            ->orderBy('p.carteirinhaValidaAte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<PosOperatorioPaciente> */
    public function findRetornosNoDia(Empresa $empresa, \DateTimeImmutable $dia): array
    {
        $marcos = [7, 14, 21];
        $result = [];

        foreach ($this->findRecentByEmpresa($empresa, 200, 0) as $paciente) {
            $cirurgia = $paciente->getDataCirurgia();
            if ($cirurgia === null) {
                continue;
            }
            foreach ($marcos as $marco) {
                $retorno = $cirurgia->modify(sprintf('+%d days', $marco));
                if ($retorno->format('Y-m-d') === $dia->format('Y-m-d')) {
                    $result[] = $paciente;
                    break;
                }
            }
        }

        return $result;
    }

    /** @return list<PosOperatorioPaciente> */
    public function findSandboxByEmpresa(Empresa $empresa): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->andWhere('p.isSandbox = true')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
