<?php

namespace App\Service;

use App\Form\LgpdSolicitacaoFormType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class LgpdRequestMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private PlatformConfigService $platformConfig,
        private TransactionalEmailComposer $emailComposer,
        private string $mailerFrom,
    ) {}

    /**
     * @param array{nome: string, email: string, tipo: string, mensagem: string} $data
     */
    public function send(array $data): void
    {
        $destino = $this->platformConfig->getEncarregadoDadosEmail();
        if ($destino === '') {
            throw new \RuntimeException('Configure o e-mail LGPD ou suporte em Admin → LGPD & Transparência.');
        }

        $tipoLabel = $this->labelForTipo($data['tipo']);

        $textBody = sprintf(
            "Nova solicitação LGPD via Canal LGPD\n\nNome: %s\nE-mail: %s\nTipo: %s\n\nMensagem:\n%s",
            $data['nome'],
            $data['email'],
            $tipoLabel,
            $data['mensagem']
        );
        $textBody = $this->emailComposer->appendPlainFooter($textBody);

        $htmlBody = sprintf(
            '<p><strong>Nova solicitação LGPD</strong> recebida pelo Canal LGPD da plataforma.</p>'
            . '<ul><li><strong>Nome:</strong> %s</li><li><strong>E-mail:</strong> %s</li>'
            . '<li><strong>Tipo:</strong> %s</li></ul><p><strong>Mensagem:</strong></p><p>%s</p>%s',
            htmlspecialchars($data['nome']),
            htmlspecialchars($data['email']),
            htmlspecialchars($tipoLabel),
            nl2br(htmlspecialchars($data['mensagem'])),
            $this->emailComposer->renderHtmlFooter()
        );

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFrom)
                ->to($destino)
                ->replyTo($data['email'])
                ->subject('[Unio LGPD] ' . $tipoLabel)
                ->text($textBody)
                ->html('<div style="font-family:sans-serif;max-width:560px;margin:0 auto">' . $htmlBody . '</div>')
        );
    }

    private function labelForTipo(string $tipo): string
    {
        foreach (LgpdSolicitacaoFormType::TIPOS as $label => $value) {
            if ($value === $tipo) {
                return $label;
            }
        }

        return $tipo;
    }
}
