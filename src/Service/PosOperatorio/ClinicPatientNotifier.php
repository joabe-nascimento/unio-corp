<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicAgendamento;
use App\Entity\PosOperatorioPaciente;
use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ClinicPatientNotifier
{
    public function __construct(
        private ClinicWebhookDispatcher $webhooks,
        private ClinicIntegrationConfigService $integrationConfig,
        private UrlGeneratorInterface $urlGenerator,
        private ClinicWhatsappService $whatsapp,
        private ClinicAgendaConfirmToken $confirmToken,
        private ?MailerInterface $mailer = null,
        private string $mailerFrom = 'noreply@unio.app',
    ) {}

    public function isEmailChannelReady(): bool
    {
        return $this->mailer !== null;
    }

    public function isWhatsappLive(): bool
    {
        return $this->whatsapp->isLive();
    }

    /**
     * @return array{email: bool, whatsapp_url: ?string, whatsapp_sent: bool, whatsapp_message_id: ?string, webhook: bool, sms_hint: bool}
     */
    public function notifyQuestionnairePending(PosOperatorioPaciente $paciente): array
    {
        $empresa = $paciente->getEmpresa();
        $portalUrl = $this->urlGenerator->generate('app_clinica_portal', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $dia = $paciente->getDiaPosOperatorio() ?? 0;
        $subject = sprintf('Questionário de hoje — %s', $paciente->getCodigo());
        $body = sprintf(
            "Olá, %s!\n\nResponda o questionário de acompanhamento de hoje (D+%d) pelo portal do paciente:\n%s\n\nEquipe clínica UNIO SAÚDE",
            $paciente->getNome(),
            $dia,
            $portalUrl,
        );

        $emailSent = $this->sendEmail($paciente->getEmailContato(), $subject, $body);
        $whatsappUrl = $this->buildWhatsappUrl($paciente, $portalUrl);
        $waText = sprintf(
            'Olá, %s! Responda o questionário de hoje pelo portal: %s',
            $paciente->getNome(),
            $portalUrl,
        );
        $waResult = $this->whatsapp->send($empresa, $paciente->getTelefoneContato(), $waText, [
            'event' => 'questionario_pendente',
            'paciente_id' => $paciente->getId(),
            'template_params' => [
                $paciente->getNome(),
                (string) $dia,
                $portalUrl,
            ],
        ]);

        $integration = $this->integrationConfig->get($empresa);
        $webhookSent = $this->webhooks->dispatch($empresa, 'questionario_pendente', [
            'paciente_id' => $paciente->getId(),
            'codigo' => $paciente->getCodigo(),
            'nome' => $paciente->getNome(),
            'dia_pos' => $dia,
            'telefone' => $paciente->getTelefoneContato(),
            'email' => $paciente->getEmailContato(),
            'portal_url' => $portalUrl,
            'whatsapp_url' => $whatsappUrl,
            'whatsapp_sent' => $waResult->sent,
            'sms_enabled' => $integration['lembretes_sms'],
        ]);

        return [
            'email' => $emailSent,
            'whatsapp_url' => $whatsappUrl,
            'whatsapp_sent' => $waResult->sent,
            'whatsapp_message_id' => $waResult->providerMessageId,
            'webhook' => $webhookSent,
            'sms_hint' => $integration['lembretes_sms'] && $webhookSent,
        ];
    }

    /**
     * Confirmação de horário (D-1): e-mail + WhatsApp API (se live) + wa.me + webhook.
     *
     * @return array{email: bool, whatsapp_url: ?string, whatsapp_sent: bool, whatsapp_message_id: ?string, webhook: bool}
     */
    public function notifyAgendaConfirmacao(ClinicAgendamento $agendamento): array
    {
        $paciente = $agendamento->getPaciente();
        $empresa = $agendamento->getEmpresa();
        $whatsappUrl = $this->confirmWhatsappUrlForAgendamento($agendamento);
        $quando = $agendamento->getInicio()->format('d/m/Y \à\s H:i');
        $titulo = $agendamento->getTitulo() ?: 'consulta';
        $medico = $agendamento->getMedico()?->getNome();
        $primeiro = explode(' ', trim($paciente->getNome()))[0] ?: 'paciente';

        $confirmUrl = $this->confirmUrlForAgendamento($agendamento);

        $subject = sprintf('Confirme seu horário — %s', $paciente->getCodigo());
        $body = sprintf(
            "Olá, %s!\n\nConfirmando seu horário:\n%s\n%s%s\n\nConfirme pelo link:\n%s\n\nOu responda CONFIRMO / REMARCAR.\n\nEquipe clínica",
            $primeiro,
            $titulo,
            $quando,
            $medico ? "\nCom: ".$medico : '',
            $confirmUrl,
        );

        $waText = sprintf(
            "Olá, %s! Confirmando seu horário na clínica:\n\n%s\n%s%s\n\nConfirme aqui: %s\n\nOu responda *CONFIRMO* / *REMARCAR*.",
            $primeiro,
            $titulo,
            $quando,
            $medico ? "\nCom: ".$medico : '',
            $confirmUrl,
        );

        $emailSent = $this->sendEmail($paciente->getEmailContato(), $subject, $body);
        $waResult = $this->whatsapp->send($empresa, $paciente->getTelefoneContato(), $waText, [
            'event' => 'agenda_confirmacao',
            'paciente_id' => $paciente->getId(),
            'agendamento_id' => $agendamento->getId(),
            'template_params' => [
                $primeiro,
                $titulo,
                $quando,
                $medico ?: 'equipe clínica',
                $confirmUrl,
            ],
        ]);

        $webhookSent = $this->webhooks->dispatch($empresa, 'agenda_confirmacao', [
            'agendamento_id' => $agendamento->getId(),
            'paciente_id' => $paciente->getId(),
            'codigo' => $paciente->getCodigo(),
            'nome' => $paciente->getNome(),
            'telefone' => $paciente->getTelefoneContato(),
            'email' => $paciente->getEmailContato(),
            'inicio' => $agendamento->getInicio()->format(\DateTimeInterface::ATOM),
            'titulo' => $titulo,
            'confirm_url' => $confirmUrl,
            'whatsapp_url' => $whatsappUrl,
            'whatsapp_sent' => $waResult->sent,
        ]);

        return [
            'email' => $emailSent,
            'whatsapp_url' => $whatsappUrl,
            'whatsapp_sent' => $waResult->sent,
            'whatsapp_message_id' => $waResult->providerMessageId,
            'webhook' => $webhookSent,
        ];
    }

    /**
     * Lembrete de marco da Trilha (ex.: D−7, D−3).
     *
     * @return array{email: bool, whatsapp_url: ?string, whatsapp_sent: bool, whatsapp_message_id: ?string, webhook: bool}
     */
    public function notifyTrilhaMarco(PosOperatorioPaciente $paciente, int $dia, string $item): array
    {
        $empresa = $paciente->getEmpresa();
        $portalUrl = $this->urlGenerator->generate('app_clinica_portal', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $label = PosOperatorioPaciente::formatDiaRelativoLabel($dia);
        $primeiro = explode(' ', trim($paciente->getNome()))[0] ?: 'paciente';

        $subject = sprintf('Trilha Unio %s — %s', $label, $paciente->getCodigo());
        $body = sprintf(
            "Olá, %s!\n\nMarco %s da sua preparação:\n%s\n\nAcesse o portal do paciente:\n%s\n\nEquipe clínica UNIO SAÚDE",
            $primeiro,
            $label,
            $item,
            $portalUrl,
        );
        $waText = sprintf(
            "Olá, %s! Trilha Unio %s: %s\n\nPortal: %s",
            $primeiro,
            $label,
            $item,
            $portalUrl,
        );

        $emailSent = $this->sendEmail($paciente->getEmailContato(), $subject, $body);
        $whatsappUrl = $this->buildWhatsappLink($paciente->getTelefoneContato(), $waText);
        $waResult = $this->whatsapp->send($empresa, $paciente->getTelefoneContato(), $waText, [
            'event' => 'trilha_marco',
            'paciente_id' => $paciente->getId(),
            'dia' => $dia,
            'template_params' => [$primeiro, $label, $item, $portalUrl],
        ]);
        $webhookSent = $this->webhooks->dispatch($empresa, 'trilha_marco', [
            'paciente_id' => $paciente->getId(),
            'codigo' => $paciente->getCodigo(),
            'nome' => $paciente->getNome(),
            'dia' => $dia,
            'item' => $item,
            'portal_url' => $portalUrl,
            'whatsapp_url' => $whatsappUrl,
            'whatsapp_sent' => $waResult->sent,
        ]);

        return [
            'email' => $emailSent,
            'whatsapp_url' => $whatsappUrl,
            'whatsapp_sent' => $waResult->sent,
            'whatsapp_message_id' => $waResult->providerMessageId,
            'webhook' => $webhookSent,
        ];
    }

    public function confirmUrlForAgendamento(ClinicAgendamento $agendamento): string
    {
        return $this->urlGenerator->generate(
            'app_clinica_agenda_confirmar',
            ['token' => $this->confirmToken->issue($agendamento)],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    public function confirmWhatsappUrlForAgendamento(ClinicAgendamento $agendamento): ?string
    {
        $paciente = $agendamento->getPaciente();
        $primeiro = explode(' ', trim($paciente->getNome()))[0] ?: 'paciente';
        $medico = $agendamento->getMedico()?->getNome();
        $titulo = $agendamento->getTitulo() ?: 'consulta';
        $quando = $agendamento->getInicio()->format('d/m/Y \à\s H:i');
        $confirmUrl = $this->confirmUrlForAgendamento($agendamento);

        $text = sprintf(
            "Olá, %s! Confirmando seu horário na clínica:\n\n%s\n%s%s\n\nConfirme aqui: %s\n\nOu responda *CONFIRMO* / *REMARCAR*.",
            $primeiro,
            $titulo,
            $quando,
            $medico ? "\nCom: ".$medico : '',
            $confirmUrl,
        );

        return $this->buildWhatsappLink($paciente->getTelefoneContato(), $text);
    }

    private function sendEmail(?string $to, string $subject, string $body): bool
    {
        if ($this->mailer === null || $to === null || trim($to) === '') {
            return false;
        }

        try {
            $this->mailer->send((new Email())
                ->from($this->mailerFrom)
                ->to($to)
                ->subject($subject)
                ->text($body));

            return true;
        } catch (TransportExceptionInterface) {
            return false;
        }
    }

    private function buildWhatsappUrl(PosOperatorioPaciente $paciente, string $portalUrl): ?string
    {
        $text = sprintf(
            'Olá, %s! Responda o questionário de hoje pelo portal: %s',
            $paciente->getNome(),
            $portalUrl,
        );

        return $this->buildWhatsappLink($paciente->getTelefoneContato(), $text);
    }

    /** @internal also used by ops panels for wa.me shortcuts */
    public function buildWhatsappLink(?string $telefone, string $text): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $telefone) ?? '';
        if (strlen($phone) < 10) {
            return null;
        }

        if (!str_starts_with($phone, '55')) {
            $phone = '55' . ltrim($phone, '0');
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($text);
    }
}
