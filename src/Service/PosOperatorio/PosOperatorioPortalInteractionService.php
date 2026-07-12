<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioEventoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PosOperatorioPortalInteractionService
{
    private const MOTIVO_AJUDA = 'Paciente solicitou ajuda pelo portal';

    public function __construct(
        private PosOperatorioAlertService $alertService,
        private PosOperatorioAlertaRepository $alertaRepo,
        private PosOperatorioEventoRepository $eventoRepo,
        private PosOperatorioEventRecorder $events,
        private EntityManagerInterface $em,
    ) {}

    /** @return array{created: bool, already_open: bool} */
    public function requestHelp(PosOperatorioPaciente $paciente, User $pacienteUser, ?string $mensagem = null): array
    {
        if ($this->alertaRepo->hasOpenAlertWithMotivo($paciente, self::MOTIVO_AJUDA)) {
            return ['created' => false, 'already_open' => true];
        }

        $motivo = self::MOTIVO_AJUDA;
        if ($mensagem !== null && trim($mensagem) !== '') {
            $motivo .= ': ' . mb_substr(trim($mensagem), 0, 200);
        }

        $this->alertService->createFromTriage($paciente, 'P2', $motivo, $pacienteUser);
        $this->events->record(
            $paciente,
            PosOperatorioEvento::TIPO_CHAT,
            'Você pediu ajuda à equipe clínica.',
            $pacienteUser,
        );
        $this->em->flush();

        return ['created' => true, 'already_open' => false];
    }

    public function confirmRetorno(PosOperatorioPaciente $paciente, User $pacienteUser): bool
    {
        $today = new \DateTimeImmutable('today');
        if ($this->eventoRepo->hasRetornoConfirmadoOnDate($paciente, $today)) {
            return false;
        }

        $this->events->record(
            $paciente,
            PosOperatorioEvento::TIPO_RETORNO,
            'Retorno ambulatorial confirmado pelo paciente.',
            $pacienteUser,
        );
        $this->em->flush();

        return true;
    }

    public function postMessage(PosOperatorioPaciente $paciente, User $autor, string $texto): void
    {
        $texto = trim($texto);
        if ($texto === '') {
            throw new \InvalidArgumentException('Mensagem vazia.');
        }

        $this->events->record(
            $paciente,
            PosOperatorioEvento::TIPO_CHAT,
            mb_substr($texto, 0, 500),
            $autor,
        );
        $this->em->flush();
    }
}
