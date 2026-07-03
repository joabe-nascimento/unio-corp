<?php

namespace App\MessageHandler\PosOperatorio;

use App\Entity\PosOperatorioQuestionarioResposta;
use App\Message\PosOperatorio\QuestionarioSubmetido;
use App\Repository\PosOperatorioPacienteRepository;
use App\Repository\PosOperatorioQuestionarioRespostaRepository;
use App\Service\PosOperatorio\PosOperatorioAlertService;
use App\Service\PosOperatorio\PosOperatorioTriageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessQuestionarioSubmetidoHandler
{
    public function __construct(
        private PosOperatorioQuestionarioRespostaRepository $questionarioRepo,
        private PosOperatorioPacienteRepository $pacienteRepo,
        private PosOperatorioTriageService $triage,
        private PosOperatorioAlertService $alertService,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(QuestionarioSubmetido $message): void
    {
        $qr = $this->questionarioRepo->find($message->questionarioRespostaId);
        if (!$qr instanceof PosOperatorioQuestionarioResposta) {
            return;
        }

        $paciente = $this->pacienteRepo->find($message->pacienteId);
        if ($paciente === null) {
            return;
        }

        $result = $this->triage->evaluate($paciente, $qr->getRespostas());
        $prioridade = $result['prioridade'];

        if (\in_array($prioridade, ['P1', 'P2', 'P3'], true)) {
            $alerta = $this->alertService->createFromTriage($paciente, $prioridade, $result['motivo']);
            if ($alerta !== null) {
                $qr->setAlertaGerado(true);
                $this->em->flush();
            }
        }
    }
}
