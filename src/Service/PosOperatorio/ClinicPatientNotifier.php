<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioPaciente;
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
        private ?MailerInterface $mailer = null,
        private string $mailerFrom = 'noreply@unio.app',
    ) {}

    public function isEmailChannelReady(): bool
    {
        return $this->mailer !== null;
    }

    /** @return array{email: bool, whatsapp_url: ?string, webhook: bool, sms_hint: bool} */
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
            'sms_enabled' => $integration['lembretes_sms'],
        ]);

        return [
            'email' => $emailSent,
            'whatsapp_url' => $whatsappUrl,
            'webhook' => $webhookSent,
            'sms_hint' => $integration['lembretes_sms'] && $webhookSent,
        ];
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
