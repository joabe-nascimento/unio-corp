<?php

namespace App\Repository;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicSala;
use App\Entity\Empresa;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicAgendamento> */
class ClinicAgendamentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicAgendamento::class);
    }

    /**
     * @return list<ClinicAgendamento>
     */
    public function findByEmpresaAndInterval(
        Empresa $empresa,
        \DateTimeImmutable $inicio,
        \DateTimeImmutable $fim,
        ?User $medico = null,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.inicio >= :inicio')
            ->andWhere('a.inicio < :fim')
            ->setParameter('empresa', $empresa)
            ->setParameter('inicio', $inicio)
            ->setParameter('fim', $fim)
            ->orderBy('a.inicio', 'ASC');

        if ($medico !== null) {
            $qb->andWhere('a.medico = :medico')
                ->setParameter('medico', $medico);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicAgendamento
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /**
     * Horários de amanhã ainda marcados/confirmados sem lembrete de confirmação no dia.
     *
     * @return list<ClinicAgendamento>
     */
    public function findPendingConfirmacaoReminders(
        Empresa $empresa,
        \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd,
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.inicio >= :inicio')
            ->andWhere('a.inicio < :fim')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.lembreteConfirmacaoEm IS NULL')
            ->setParameter('empresa', $empresa)
            ->setParameter('inicio', $dayStart)
            ->setParameter('fim', $dayEnd)
            ->setParameter('statuses', [
                ClinicAgendamento::STATUS_MARCADO,
                ClinicAgendamento::STATUS_CONFIRMADO,
            ])
            ->orderBy('a.inicio', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ClinicAgendamento>
     */
    public function findForConfirmacaoPanel(
        Empresa $empresa,
        \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd,
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.inicio >= :inicio')
            ->andWhere('a.inicio < :fim')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('inicio', $dayStart)
            ->setParameter('fim', $dayEnd)
            ->setParameter('statuses', [
                ClinicAgendamento::STATUS_MARCADO,
                ClinicAgendamento::STATUS_CONFIRMADO,
            ])
            ->orderBy('a.inicio', 'ASC')
            ->setMaxResults(40)
            ->getQuery()
            ->getResult();
    }

    /**
     * Agendamentos de hoje ainda em espera de recepção (marcado/confirmado).
     *
     * @return list<ClinicAgendamento>
     */
    public function findTodayAwaitingReception(
        Empresa $empresa,
        \App\Entity\PosOperatorioPaciente $paciente,
        \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd,
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.paciente = :paciente')
            ->andWhere('a.inicio >= :inicio')
            ->andWhere('a.inicio < :fim')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('paciente', $paciente)
            ->setParameter('inicio', $dayStart)
            ->setParameter('fim', $dayEnd)
            ->setParameter('statuses', [
                ClinicAgendamento::STATUS_MARCADO,
                ClinicAgendamento::STATUS_CONFIRMADO,
            ])
            ->orderBy('a.inicio', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Próximos horários marcados (hoje/amanhã) para confirmação inbound por telefone.
     *
     * @return list<ClinicAgendamento>
     */
    public function findMarcadosForPhoneConfirm(
        Empresa $empresa,
        \DateTimeImmutable $inicio,
        \DateTimeImmutable $fim,
    ): array {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.paciente', 'p')
            ->addSelect('p')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.inicio >= :inicio')
            ->andWhere('a.inicio < :fim')
            ->andWhere('a.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('inicio', $inicio)
            ->setParameter('fim', $fim)
            ->setParameter('status', ClinicAgendamento::STATUS_MARCADO)
            ->orderBy('a.inicio', 'ASC')
            ->setMaxResults(80)
            ->getQuery()
            ->getResult();
    }

    /** Conflito de sala: outro horário ativo com intervalo sobreposto. */
    public function hasSalaOverlap(
        Empresa $empresa,
        ClinicSala $sala,
        \DateTimeImmutable $inicio,
        \DateTimeImmutable $fim,
        ?int $ignoreId = null,
    ): bool {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.sala = :sala')
            ->andWhere('a.status NOT IN (:cancelados)')
            ->andWhere('a.inicio < :fim')
            ->andWhere('a.fim > :inicio')
            ->setParameter('empresa', $empresa)
            ->setParameter('sala', $sala)
            ->setParameter('inicio', $inicio)
            ->setParameter('fim', $fim)
            ->setParameter('cancelados', [
                ClinicAgendamento::STATUS_CANCELADO,
                ClinicAgendamento::STATUS_FALTOU,
            ]);

        if ($ignoreId !== null && $ignoreId > 0) {
            $qb->andWhere('a.id != :ignore')->setParameter('ignore', $ignoreId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
