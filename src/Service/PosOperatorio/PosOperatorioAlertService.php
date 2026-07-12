<?php

namespace App\Service\PosOperatorio;

use App\Domain\Event\DomainEventBus;
use App\Entity\Empresa;
use App\Entity\PosOperatorioAlerta;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Message\PosOperatorio\AlertaGerado;
use App\Repository\PosOperatorioAlertaRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PosOperatorioAlertService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PosOperatorioAlertaRepository $repository,
        private PosOperatorioEventRecorder $events,
        private DomainEventBus $domainEvents,
        private PosOperatorioMercurePublisher $mercure,
        private ClinicPolicyConfigService $policy,
        private ClinicDutyRosterService $duty,
        private ClinicChannelDispatcher $channels,
    ) {}

    public function createFromTriage(
        PosOperatorioPaciente $paciente,
        string $prioridade,
        string $motivo,
        ?User $autor = null,
    ): ?PosOperatorioAlerta {
        if (!\in_array($prioridade, PosOperatorioAlerta::PRIORIDADES, true) || $prioridade === 'P4') {
            return null;
        }

        $empresa = $paciente->getEmpresa();
        $slaMin = $this->policy->slaMinutes($empresa, $prioridade);
        $alerta = (new PosOperatorioAlerta())
            ->setEmpresa($empresa)
            ->setPaciente($paciente)
            ->setPrioridade($prioridade)
            ->setMotivo($motivo)
            ->setStatus(PosOperatorioAlerta::STATUS_ABERTO)
            ->setSlaLimiteEm(new \DateTimeImmutable(sprintf('+%d minutes', $slaMin)));

        $responsavel = $this->duty->pickForAlert($empresa, $paciente, $prioridade);
        if ($responsavel instanceof User) {
            $alerta->setResponsavel($responsavel);
        }

        $paciente->setStatus(PosOperatorioPaciente::STATUS_ALERTA);

        $this->em->persist($alerta);
        $this->events->record(
            $paciente,
            \App\Entity\PosOperatorioEvento::TIPO_ALERTA,
            sprintf('Alerta %s: %s', $prioridade, $motivo),
            $autor,
        );
        $this->em->flush();

        if ($responsavel instanceof User && $prioridade === 'P1') {
            $this->channels->notifyTeam(
                $empresa,
                $responsavel,
                'alerta_clinico',
                sprintf('P1 — %s', $paciente->getCodigo()),
                $motivo,
                'app_pos_operatorio_sala_critica',
                'fa-triangle-exclamation',
                'danger',
            );
            $this->channels->emitWebhook($empresa, 'alerta_p1', [
                'alerta_id' => $alerta->getId(),
                'paciente_codigo' => $paciente->getCodigo(),
                'motivo' => $motivo,
                'responsavel_id' => $responsavel->getId(),
            ]);
        }

        $this->domainEvents->publish(new AlertaGerado(
            (int) $alerta->getId(),
            (int) $empresa->getId(),
            $prioridade,
            $paciente->getCodigo(),
            $motivo,
        ));
        $this->publishQueueRefresh($empresa);

        return $alerta;
    }

    /** @return list<PosOperatorioAlerta> */
    public function findAbertos(Empresa $empresa, int $limit = 20): array
    {
        return $this->repository->findAbertosByEmpresa($empresa, $limit);
    }

    public function countAbertos(Empresa $empresa): int
    {
        return $this->repository->countAbertosByEmpresa($empresa);
    }

    public function getById(Empresa $empresa, int $id): ?PosOperatorioAlerta
    {
        $alerta = $this->repository->find($id);

        return ($alerta instanceof PosOperatorioAlerta && $alerta->getEmpresa()->getId() === $empresa->getId())
            ? $alerta
            : null;
    }

    public function claim(PosOperatorioAlerta $alerta, User $user): void
    {
        $alerta->setResponsavel($user);
        if ($alerta->getStatus() === PosOperatorioAlerta::STATUS_ABERTO) {
            $alerta->setStatus(PosOperatorioAlerta::STATUS_EM_ATENDIMENTO);
        }
        $this->events->record(
            $alerta->getPaciente(),
            \App\Entity\PosOperatorioEvento::TIPO_ALERTA,
            sprintf('Alerta %s assumido por %s', $alerta->getPrioridade(), $user->getNome() ?? $user->getEmail()),
            $user,
        );
        $this->em->flush();
        $this->publishQueueRefresh($alerta->getEmpresa());
    }

    public function resolve(PosOperatorioAlerta $alerta, User $user, ?string $nota = null): void
    {
        $alerta->setStatus(PosOperatorioAlerta::STATUS_RESOLVIDO)
            ->setResolvidoEm(new \DateTimeImmutable());

        $paciente = $alerta->getPaciente();
        $outrosAbertos = $this->repository->countAbertosByPaciente($paciente, (int) $alerta->getId());
        if ($outrosAbertos === 0 && $paciente->getStatus() === PosOperatorioPaciente::STATUS_ALERTA) {
            $paciente->setStatus(PosOperatorioPaciente::STATUS_ATIVO);
        }

        $desc = sprintf('Alerta %s resolvido', $alerta->getPrioridade());
        if ($nota) {
            $desc .= ': ' . $nota;
            $this->events->record(
                $paciente,
                \App\Entity\PosOperatorioEvento::TIPO_CHAT,
                $nota,
                $user,
            );
        }
        $this->events->record($paciente, \App\Entity\PosOperatorioEvento::TIPO_ALERTA, $desc, $user);
        $this->em->flush();
        $this->publishQueueRefresh($alerta->getEmpresa());
    }

    private function publishQueueRefresh(Empresa $empresa): void
    {
        $this->mercure->publishAlertasUpdate((int) $empresa->getId(), [
            'action' => 'queue_refresh',
            'abertos' => $this->repository->countAbertosByEmpresa($empresa),
        ]);
    }
}
