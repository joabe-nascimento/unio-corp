<?php

namespace App\Controller\Clinic;

use App\Entity\ClinicAgendamento;
use App\Repository\ClinicAgendamentoRepository;
use App\Service\PosOperatorio\ClinicAgendaConfirmToken;
use App\Service\PosOperatorio\ClinicAgendaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clinica/agenda')]
final class AgendaConfirmController extends AbstractController
{
    public function __construct(
        private ClinicAgendaConfirmToken $tokens,
        private ClinicAgendamentoRepository $agendamentos,
        private ClinicAgendaService $agenda,
    ) {}

    #[Route('/confirmar/{token}', name: 'app_clinica_agenda_confirmar', methods: ['GET'])]
    public function confirmar(string $token): Response
    {
        $parsed = $this->tokens->parse($token);
        if ($parsed === null) {
            return $this->render('modules/pos-operatorio/agenda/confirm_public.html.twig', [
                'ok' => false,
                'message' => 'Link inválido ou expirado. Peça um novo lembrete à clínica.',
            ]);
        }

        $agendamento = $this->agendamentos->find($parsed['agendamento_id']);
        if (!$agendamento instanceof ClinicAgendamento
            || (int) $agendamento->getEmpresa()->getId() !== $parsed['empresa_id']
        ) {
            return $this->render('modules/pos-operatorio/agenda/confirm_public.html.twig', [
                'ok' => false,
                'message' => 'Horário não encontrado.',
            ]);
        }

        $empresa = $agendamento->getEmpresa();
        $status = $agendamento->getStatus();

        if ($status === ClinicAgendamento::STATUS_CONFIRMADO) {
            return $this->render('modules/pos-operatorio/agenda/confirm_public.html.twig', [
                'ok' => true,
                'already' => true,
                'agendamento' => $agendamento,
                'message' => 'Seu horário já estava confirmado. Obrigado!',
            ]);
        }

        if ($status !== ClinicAgendamento::STATUS_MARCADO) {
            return $this->render('modules/pos-operatorio/agenda/confirm_public.html.twig', [
                'ok' => false,
                'message' => 'Este horário não pode mais ser confirmado por este link.',
                'agendamento' => $agendamento,
            ]);
        }

        try {
            $this->agenda->changeStatus($agendamento, $empresa, ClinicAgendamento::STATUS_CONFIRMADO);
        } catch (\Throwable) {
            return $this->render('modules/pos-operatorio/agenda/confirm_public.html.twig', [
                'ok' => false,
                'message' => 'Não foi possível confirmar agora. Tente novamente ou fale com a clínica.',
            ]);
        }

        return $this->render('modules/pos-operatorio/agenda/confirm_public.html.twig', [
            'ok' => true,
            'already' => false,
            'agendamento' => $agendamento,
            'message' => 'Horário confirmado com sucesso. Até breve!',
        ]);
    }
}
