<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoPrazo;
use App\Entity\JuridicoPrazoAlertaLog;
use App\Repository\JuridicoPrazoAlertaLogRepository;
use App\Repository\JuridicoPrazoConfigRepository;
use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappService;
use App\Service\TransactionalEmailComposer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Alertas em cascata de prazos: D-7, D-3, D-1, hoje e vencido — via WhatsApp e e-mail.
 */
final class JuridicoPrazoAlertaService
{
  private const NIVEIS = [7 => 'd7', 3 => 'd3', 1 => 'd1', 0 => 'hoje'];

    public function __construct(
        private JuridicoPrazoConfigRepository $configRepo,
        private JuridicoPrazoAlertaLogRepository $logRepo,
        private ClinicWhatsappService $whatsapp,
        private EntityManagerInterface $em,
        private TransactionalEmailComposer $emailComposer,
        private LoggerInterface $logger,
        private ?MailerInterface $mailer = null,
    ) {
    }

    /**
     * Varre prazos pendentes do escritório e dispara alertas externos (D-7, D-3, D-1, hoje, vencido).
     */
    public function processarEmpresa(Empresa $empresa, iterable $prazos): int
    {
        $total = 0;
        foreach ($prazos as $prazo) {
            if ($prazo instanceof JuridicoPrazo && !$prazo->isCumprido()) {
                $total += $this->processarPrazo($empresa, $prazo);
            }
        }

        return $total;
    }

    /**
     * Processa alertas externos para um prazo. Retorna quantos canais foram disparados.
     */
    public function processarPrazo(Empresa $empresa, JuridicoPrazo $prazo): int
    {
        if ($prazo->isCumprido()) {
            return 0;
        }

        $dias = $prazo->getDiasRestantes();
        $nivel = $this->resolverNivel($dias);
        if ($nivel === null) {
            return 0;
        }

        $config = $this->configRepo->getOrCreate($empresa);
        $titulo = $this->tituloParaNivel($nivel, $prazo);
        $mensagem = $this->mensagemParaNivel($nivel, $prazo, $dias);
        $enviados = 0;

        if ($config->isAlertaWhatsapp()) {
            $telefone = $config->getTelefoneAlerta();
            if ($telefone !== null && trim($telefone) !== '' && !$this->logRepo->jaEnviado($prazo, $nivel, JuridicoPrazoAlertaLog::CANAL_WHATSAPP)) {
                if ($this->enviarWhatsapp($empresa, $telefone, $titulo, $mensagem)) {
                    $this->registrarLog($prazo, $nivel, JuridicoPrazoAlertaLog::CANAL_WHATSAPP);
                    ++$enviados;
                }
            }
        }

        if ($config->isAlertaEmail()) {
            $email = $config->getEmailAlerta();
            if ($email !== null && filter_var($email, \FILTER_VALIDATE_EMAIL) && !$this->logRepo->jaEnviado($prazo, $nivel, JuridicoPrazoAlertaLog::CANAL_EMAIL)) {
                if ($this->enviarEmail($email, $titulo, $mensagem)) {
                    $this->registrarLog($prazo, $nivel, JuridicoPrazoAlertaLog::CANAL_EMAIL);
                    ++$enviados;
                }
            }
        }

        return $enviados;
    }

    /** @param array<string, mixed> $data */
    public function salvarConfig(Empresa $empresa, array $data): void
    {
        $config = $this->configRepo->getOrCreate($empresa);
        $config->setAlertaWhatsapp((bool) ($data['alerta_whatsapp'] ?? false));
        $config->setAlertaEmail((bool) ($data['alerta_email'] ?? false));

        $telefone = preg_replace('/\D+/', '', (string) ($data['telefone_alerta'] ?? '')) ?? '';
        $config->setTelefoneAlerta($telefone !== '' ? $telefone : null);

        $email = trim((string) ($data['email_alerta'] ?? ''));
        $config->setEmailAlerta($email !== '' ? $email : null);
        $config->touch();
        $this->em->flush();
    }

    private function resolverNivel(int $dias): ?string
    {
        if ($dias < 0) {
            return 'vencido';
        }

        return self::NIVEIS[$dias] ?? null;
    }

    private function tituloParaNivel(string $nivel, JuridicoPrazo $prazo): string
    {
        return match ($nivel) {
            'vencido' => sprintf('Prazo vencido: %s', $prazo->getTipo()),
            'hoje' => sprintf('Prazo vence hoje: %s', $prazo->getTipo()),
            'd1' => sprintf('Prazo amanhã: %s', $prazo->getTipo()),
            'd3' => sprintf('Prazo em 3 dias: %s', $prazo->getTipo()),
            default => sprintf('Prazo em 7 dias: %s', $prazo->getTipo()),
        };
    }

    private function mensagemParaNivel(string $nivel, JuridicoPrazo $prazo, int $dias): string
    {
        $processo = $prazo->getProcesso()?->getNumero();
        $data = $prazo->getDataLimite()->format('d/m/Y');
        $base = $processo ? "Processo {$processo}. " : '';

        return match ($nivel) {
            'vencido' => $base . sprintf('"%s" venceu há %d dia(s) (%s) e ainda não foi cumprido.', $prazo->getTipo(), abs($dias), $data),
            'hoje' => $base . sprintf('"%s" vence hoje (%s).', $prazo->getTipo(), $data),
            'd1' => $base . sprintf('"%s" vence amanhã (%s).', $prazo->getTipo(), $data),
            'd3' => $base . sprintf('"%s" vence em 3 dias (%s).', $prazo->getTipo(), $data),
            default => $base . sprintf('"%s" vence em 7 dias (%s).', $prazo->getTipo(), $data),
        };
    }

    private function enviarWhatsapp(Empresa $empresa, string $telefone, string $titulo, string $mensagem): bool
    {
        if (!$this->whatsapp->isLive()) {
            $this->logger->info('WhatsApp alerta de prazo pulado — Meta Cloud não configurado');

            return false;
        }

        try {
            $texto = sprintf("Unio Jurídico — %s\n%s\nAcesse Prazos & Diligências.", $titulo, $mensagem);
            $this->whatsapp->send($empresa, $telefone, $texto, ['event' => 'juridico_prazo_alerta']);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Falha ao enviar WhatsApp de prazo: {msg}', ['msg' => $e->getMessage()]);

            return false;
        }
    }

    private function enviarEmail(string $to, string $titulo, string $mensagem): bool
    {
        if ($this->mailer === null) {
            return false;
        }

        $from = $_ENV['MAILER_FROM'] ?? 'noreply@unio.app';
        $footerHtml = $this->emailComposer->renderHtmlFooter();
        $body = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="font-family:sans-serif;background:#F1F5F9;margin:0;padding:20px;">
  <div style="max-width:520px;margin:auto;background:#fff;border-radius:10px;overflow:hidden;">
    <div style="background:#7a2130;color:#fff;padding:20px 24px;">
      <span style="font-size:13px;opacity:.8;">Unio Jurídico</span>
      <h2 style="margin:4px 0 0;font-size:18px;">{$titulo}</h2>
    </div>
    <div style="padding:24px;color:#334155;font-size:15px;line-height:1.6;">
      <p>{$mensagem}</p>
    </div>
    {$footerHtml}
  </div>
</body></html>
HTML;

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->to($to)
                    ->subject('[Unio Jurídico] ' . $titulo)
                    ->html($body),
            );

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Falha ao enviar e-mail de prazo: {msg}', ['msg' => $e->getMessage()]);

            return false;
        }
    }

    private function registrarLog(JuridicoPrazo $prazo, string $nivel, string $canal): void
    {
        $log = new JuridicoPrazoAlertaLog();
        $log->setPrazo($prazo)->setNivel($nivel)->setCanal($canal);
        $this->em->persist($log);
        $this->em->flush();
    }
}
