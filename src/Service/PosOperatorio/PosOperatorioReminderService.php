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

        private EntityManagerInterface $em,

    ) {}



    /** @return array{enviados: int, ignorados: int, sem_medico: int} */

    public function sendPendingQuestionnaireReminders(Empresa $empresa): array

    {

        $today = new \DateTimeImmutable('today');

        $enviados = 0;

        $ignorados = 0;

        $semMedico = 0;



        foreach ($this->pacienteRepo->findActiveWithoutQuestionarioToday($empresa, $today) as $paciente) {

            if ($this->eventoRepo->hasLembreteOnDate($paciente, $today)) {

                ++$ignorados;

                continue;

            }



            $destinatario = $paciente->getMedicoResponsavel();

            if (!$destinatario instanceof User) {

                ++$semMedico;

                continue;

            }



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



            $this->events->record(

                $paciente,

                PosOperatorioEvento::TIPO_LEMBRETE,

                'Lembrete automático de questionário pendente',

                null,

            );

            ++$enviados;

        }



        if ($enviados > 0) {

            $this->em->flush();

        }



        return ['enviados' => $enviados, 'ignorados' => $ignorados, 'sem_medico' => $semMedico];

    }

}

