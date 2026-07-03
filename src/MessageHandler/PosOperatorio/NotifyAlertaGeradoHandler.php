<?php

namespace App\MessageHandler\PosOperatorio;

use App\Entity\PosOperatorioAlerta;
use App\Message\PosOperatorio\AlertaGerado;
use App\Repository\PosOperatorioAlertaRepository;
use App\Service\PlatformNotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NotifyAlertaGeradoHandler
{
    public function __construct(
        private PosOperatorioAlertaRepository $alertaRepo,
        private PlatformNotificationService $notifications,
    ) {}

    public function __invoke(AlertaGerado $message): void
    {
        $alerta = $this->alertaRepo->find($message->alertaId);
        if (!$alerta instanceof PosOperatorioAlerta) {
            return;
        }

        $paciente = $alerta->getPaciente();
        $empresa = $alerta->getEmpresa();
        $destinatario = $alerta->getResponsavel() ?? $paciente->getMedicoResponsavel();

        if ($destinatario === null) {
            return;
        }

        $severidade = match ($alerta->getPrioridade()) {
            'P1' => 'danger',
            'P2' => 'warning',
            default => 'info',
        };

        $this->notifications->notify(
            $empresa,
            $destinatario,
            'pos_operatorio',
            'alerta_clinico',
            sprintf('Alerta %s — %s', $alerta->getPrioridade(), $paciente->getCodigo()),
            $alerta->getMotivo(),
            'app_pos_operatorio_alertas',
            [],
            'fa-triangle-exclamation',
            $severidade,
        );
    }
}
