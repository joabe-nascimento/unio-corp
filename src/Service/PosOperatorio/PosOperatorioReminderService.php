<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\PosOperatorioEventoRepository;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PlatformNotificationService;
use Doctrine\ORM\EntityManagerInterface;

final class PosOperatorioReminderService
{
    public function __construct(
        private PosOperatorioPacienteRepository $pacienteRepo,
        private PosOperatorioEventoRepository $eventoRepo,
        private PosOperatorioEventRecorder $events,
        private PlatformNotificationService $notifications,
        private ClinicPatientNotifier $patientNotifier,
        private EntityManagerInterface $em,
    ) {}

    /** @return array{enviados: int, ignorados: int, sem_medico: int, pacientes: int} */
    public function sendPendingQuestionnaireReminders(Empresa $empresa): array
    {
        $today = new \DateTimeImmutable('today');
        $enviados = 0;
        $ignorados = 0;
        $semMedico = 0;
        $pacientesNotificados = 0;

        foreach ($this->pacienteRepo->findActiveWithoutQuestionarioToday($empresa, $today) as $paciente) {
            if ($this->eventoRepo->hasLembreteOnDate($paciente, $today)) {
                ++$ignorados;
                continue;
            }

            $patientResult = $this->patientNotifier->notifyQuestionnairePending($paciente);
            if ($patientResult['email'] || $patientResult['whatsapp_url'] !== null || $patientResult['webhook']) {
                ++$pacientesNotificados;
            }

            $destinatario = $paciente->getMedicoResponsavel();
            if ($destinatario instanceof User) {
                $this->notifications->notify(
                    $empresa,
                    $destinatario,
                    'pos_operatorio',
                    'lembrete_questionario',
                    sprintf('Questionário pendente — %s', $paciente->getCodigo()),
                    sprintf('%s ainda não respondeu o questionário de hoje (D+%d).', $paciente->getNome(), $paciente->getDiaPosOperatorio() ?? 0),
                    'app_pos_operatorio_questionarios',
                    [],
                    'fa-bell',
                    'info',
                );
            } else {
                ++$semMedico;
            }

            $detail = 'Lembrete automático de questionário pendente';
            if ($patientResult['email']) {
                $detail .= ' · e-mail ao paciente';
            }
            if ($patientResult['whatsapp_url'] !== null) {
                $detail .= ' · WhatsApp preparado';
            }

            $this->events->record(
                $paciente,
                PosOperatorioEvento::TIPO_LEMBRETE,
                $detail,
                null,
            );
            ++$enviados;
        }

        if ($enviados > 0) {
            $this->em->flush();
        }

        return [
            'enviados' => $enviados,
            'ignorados' => $ignorados,
            'sem_medico' => $semMedico,
            'pacientes' => $pacientesNotificados,
        ];
    }

    /** @return array{enviado: bool, motivo: string|null} */
    public function sendQuestionnaireReminder(PosOperatorioPaciente $paciente, User $autor): array
    {
        $today = new \DateTimeImmutable('today');

        if ($this->eventoRepo->hasLembreteOnDate($paciente, $today)) {
            return ['enviado' => false, 'motivo' => 'already_sent'];
        }

        $this->patientNotifier->notifyQuestionnairePending($paciente);

        $destinatario = $paciente->getMedicoResponsavel();
        if ($destinatario instanceof User) {
            $this->notifications->notify(
                $paciente->getEmpresa(),
                $destinatario,
                'pos_operatorio',
                'lembrete_questionario',
                sprintf('Questionário pendente — %s', $paciente->getCodigo()),
                sprintf('%s ainda não respondeu o questionário de hoje (D+%d).', $paciente->getNome(), $paciente->getDiaPosOperatorio() ?? 0),
                'app_pos_operatorio_questionarios',
                [],
                'fa-bell',
                'info',
            );
        }

        $this->events->record(
            $paciente,
            PosOperatorioEvento::TIPO_LEMBRETE,
            sprintf('Lembrete de questionário enviado por %s', $autor->getNome() ?? $autor->getEmail()),
            $autor,
        );
        $this->em->flush();

        return ['enviado' => true, 'motivo' => null];
    }
}
