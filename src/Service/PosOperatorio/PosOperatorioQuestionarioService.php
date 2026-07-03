<?php

namespace App\Service\PosOperatorio;

use App\Domain\Event\DomainEventBus;
use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\PosOperatorioQuestionarioResposta;
use App\Entity\User;
use App\Message\PosOperatorio\QuestionarioSubmetido;
use App\Repository\PosOperatorioQuestionarioRespostaRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PosOperatorioQuestionarioService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PosOperatorioQuestionarioRespostaRepository $repository,
        private PosOperatorioTriageService $triage,
        private PosOperatorioEventRecorder $events,
        private DomainEventBus $domainEvents,
    ) {}

    /**
     * @param array<string, mixed> $respostas
     */
    public function submit(PosOperatorioPaciente $paciente, array $respostas, ?User $autor = null): PosOperatorioQuestionarioResposta
    {
        $today = new \DateTimeImmutable('today');
        $existing = $this->repository->findOneBy([
            'paciente' => $paciente,
            'dataReferencia' => $today,
        ]);

        $qr = $existing ?? new PosOperatorioQuestionarioResposta();
        $qr->setPaciente($paciente)
            ->setDataReferencia($today)
            ->setRespostas($respostas);

        $result = $this->triage->evaluate($paciente, $respostas);
        $qr->setScoreRisco($result['score_risco']);

        $this->em->persist($qr);
        $this->events->record(
            $paciente,
            PosOperatorioEvento::TIPO_QUESTIONARIO,
            sprintf('Questionário D+%d respondido (score %d)', $paciente->getDiaPosOperatorio() ?? 0, $result['score_risco']),
            $autor,
        );
        $this->em->flush();

        $this->domainEvents->publish(new QuestionarioSubmetido(
            (int) $qr->getId(),
            (int) $paciente->getId(),
            (int) $paciente->getEmpresa()->getId(),
            $paciente->getCodigo(),
        ));

        return $qr;
    }
}
