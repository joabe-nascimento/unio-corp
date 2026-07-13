<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Service\PlatformNotificationService;
use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappService;

/**
 * Canais plugáveis de continuidade: in-app, e-mail, WhatsApp (Meta live ou wa.me), SMS (via webhook).
 */
final class ClinicChannelDispatcher
{
    public function __construct(
        private ClinicPolicyConfigService $policy,
        private ClinicIntegrationConfigService $integrationConfig,
        private ClinicPatientNotifier $patientNotifier,
        private ClinicWebhookDispatcher $webhooks,
        private PlatformNotificationService $notifications,
        private ClinicWhatsappService $whatsapp,
    ) {}

    /**
     * @return list<array{id: string, nome: string, status: string, desc: string}>
     */
    public function channelStatuses(Empresa $empresa): array
    {
        $policy = $this->policy->get($empresa);
        $webhookOk = $this->integrationConfig->webhookConfigured($empresa);
        $waLive = $this->whatsapp->isLive();

        return [
            [
                'id' => 'in_app',
                'nome' => 'Notificação in-app',
                'status' => $policy['canais']['in_app'] ? 'active' : 'off',
                'desc' => 'Equipe na plataforma',
            ],
            [
                'id' => 'email',
                'nome' => 'E-mail ao paciente',
                'status' => !$policy['canais']['email']
                    ? 'off'
                    : ($this->patientNotifier->isEmailChannelReady() ? 'active' : 'prepared'),
                'desc' => $this->patientNotifier->isEmailChannelReady()
                    ? 'Mailer interno ativo'
                    : 'Aguardando mailer / SMTP',
            ],
            [
                'id' => 'whatsapp',
                'nome' => 'WhatsApp',
                'status' => !$policy['canais']['whatsapp']
                    ? 'off'
                    : ($waLive ? 'active' : 'prepared'),
                'desc' => $waLive
                    ? 'Meta Cloud API (envio automático)'
                    : 'Link wa.me + webhook (configure WHATSAPP_META_* para live)',
            ],
            [
                'id' => 'sms',
                'nome' => 'SMS',
                'status' => !$policy['canais']['sms']
                    ? 'off'
                    : ($webhookOk ? 'prepared' : 'waiting'),
                'desc' => $webhookOk
                    ? 'Disparo via webhook da clínica'
                    : 'Aguardando URL de webhook',
            ],
        ];
    }

    public function notifyTeam(
        Empresa $empresa,
        User $destinatario,
        string $tipo,
        string $titulo,
        string $mensagem,
        string $route = 'app_pos_operatorio_trabalho',
        string $icon = 'fa-bell',
        string $severidade = 'info',
    ): bool {
        if (!$this->policy->get($empresa)['canais']['in_app']) {
            return false;
        }

        $this->notifications->notify(
            $empresa,
            $destinatario,
            'pos_operatorio',
            $tipo,
            $titulo,
            $mensagem,
            $route,
            [],
            $icon,
            $severidade,
        );

        return true;
    }

    /**
     * @return array{email: bool, whatsapp_url: ?string, whatsapp_sent: bool, whatsapp_message_id: ?string, webhook: bool, sms_hint: bool}
     */
    public function notifyPatientQuestionnaire(PosOperatorioPaciente $paciente): array
    {
        $canais = $this->policy->get($paciente->getEmpresa())['canais'];
        if (!$canais['email'] && !$canais['whatsapp'] && !$canais['sms']) {
            return [
                'email' => false,
                'whatsapp_url' => null,
                'whatsapp_sent' => false,
                'whatsapp_message_id' => null,
                'webhook' => false,
                'sms_hint' => false,
            ];
        }

        return $this->patientNotifier->notifyQuestionnairePending($paciente);
    }

    /** @param array<string, mixed> $payload */
    public function emitWebhook(Empresa $empresa, string $event, array $payload): bool
    {
        return $this->webhooks->dispatch($empresa, $event, $payload);
    }
}
