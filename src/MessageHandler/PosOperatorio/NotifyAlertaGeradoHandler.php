<?php

namespace App\MessageHandler\PosOperatorio;

use App\Entity\PosOperatorioAlerta;
use App\Message\PosOperatorio\AlertaGerado;
use App\Repository\PosOperatorioAlertaRepository;
use App\Service\PlatformNotificationService;
use App\Service\PosOperatorio\ClinicWebhookDispatcher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NotifyAlertaGeradoHandler
{
    public function __construct(
        private PosOperatorioAlertaRepository $alertaRepo,
        private PlatformNotificationService $notifications,
        private ClinicWebhookDispatcher $webhooks,
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

        if ($destinatario !== null) {
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

        $event = $alerta->getPrioridade() === 'P1' ? 'alerta_p1' : 'alerta_clinico';
        $this->webhooks->dispatch($empresa, $event, [
            'alerta_id' => $alerta->getId(),
            'prioridade' => $alerta->getPrioridade(),
            'motivo' => $alerta->getMotivo(),
            'status' => $alerta->getStatus(),
            'paciente_id' => $paciente->getId(),
            'paciente_codigo' => $paciente->getCodigo(),
            'paciente_nome' => $paciente->getNome(),
            'telefone' => $paciente->getTelefoneContato(),
            'email' => $paciente->getEmailContato(),
        ]);
    }
}
