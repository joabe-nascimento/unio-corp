<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiNotificacao;
use App\Entity\User;
use App\Repository\TiNotificacaoRepository;
use App\Service\TransactionalEmailComposer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class TiNotificationService
{
    private const EMAIL_TIPOS = ['p1_criado', 'sla_critico', 'atribuicao', 'incidente_auto'];

    public function __construct(
        private EntityManagerInterface $em,
        private TiNotificacaoRepository $repository,
        private TransactionalEmailComposer $emailComposer,
        private ?MailerInterface $mailer = null,
    ) {}

    public function notify(
        Empresa $empresa,
        User $user,
        string $tipo,
        string $titulo,
        string $mensagem,
        ?string $link = null,
    ): void {
        $n = new TiNotificacao();
        $n->setEmpresa($empresa)
            ->setUser($user)
            ->setTipo($tipo)
            ->setTitulo($titulo)
            ->setMensagem($mensagem)
            ->setLink($link);
        $this->em->persist($n);
        $this->em->flush();

        if (\in_array($tipo, self::EMAIL_TIPOS, true) && $user->getEmail() !== null && $this->mailer !== null) {
            $this->sendEmail($user->getEmail(), $titulo, $mensagem, $link);
        }
    }

    private function sendEmail(string $to, string $titulo, string $mensagem, ?string $link): void
    {
        $from = $_ENV['MAILER_FROM'] ?? 'noreply@unio.app';
        $linkHtml = '';
        if ($link !== null && $link !== '') {
            $linkHtml = sprintf(
                '<p style="margin-top:20px;"><a href="%s" style="background:#0EA5E9;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;">Ver detalhes</a></p>',
                htmlspecialchars($link),
            );
        }

        $footerHtml = $this->emailComposer->renderHtmlFooter();

        $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:sans-serif;background:#F1F5F9;margin:0;padding:20px;">
  <div style="max-width:520px;margin:auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
    <div style="background:#0F172A;color:#fff;padding:20px 24px;">
      <span style="font-size:13px;color:#94A3B8;letter-spacing:.05em;">UNio — Núcleo TI</span>
      <h2 style="margin:4px 0 0;font-size:18px;">{$titulo}</h2>
    </div>
    <div style="padding:24px;color:#334155;font-size:15px;line-height:1.6;">
      <p>{$mensagem}</p>
      {$linkHtml}
    </div>
    {$footerHtml}
  </div>
</body>
</html>
HTML;

        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('[UNio TI] ' . $titulo)
            ->html($body);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            // Mailer not configured — silently ignore
        }
    }

    /** @return list<array<string, mixed>> */
    public function unread(Empresa $empresa, User $user, int $limit = 10): array
    {
        return array_map(
            static fn (TiNotificacao $n) => $n->toArray(),
            $this->repository->findUnreadByUser($empresa, $user, $limit),
        );
    }

    public function unreadCount(Empresa $empresa, User $user): int
    {
        return $this->repository->countUnread($empresa, $user);
    }

    /** @return array{count: int, notifications: list<array<string, mixed>>, latest_id: int, new_count: int} */
    public function pollUnread(Empresa $empresa, User $user, int $sinceId = 0, int $limit = 8): array
    {
        $all = $this->repository->findUnreadByUser($empresa, $user, $limit);
        $new = $sinceId > 0
            ? $this->repository->findUnreadSince($empresa, $user, $sinceId, $limit)
            : [];

        $latestId = 0;
        foreach ($all as $n) {
            $id = (int) ($n->getId() ?? 0);
            if ($id > $latestId) {
                $latestId = $id;
            }
        }

        return [
            'count' => $this->repository->countUnread($empresa, $user),
            'notifications' => array_map(static fn (TiNotificacao $n) => $n->toArray(), $all),
            'latest_id' => $latestId,
            'new_count' => \count($new),
        ];
    }

    public function markRead(Empresa $empresa, User $user, int $id): void
    {
        $n = $this->repository->find($id);
        if (!$n || $n->getUser()->getId() !== $user->getId() || $n->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Notificação não encontrada.');
        }
        $n->setLida(true);
        $this->em->flush();
    }

    /** @param list<User> $users */
    public function notifyMany(Empresa $empresa, array $users, string $tipo, string $titulo, string $mensagem, ?string $link = null): void
    {
        foreach ($users as $user) {
            $this->notify($empresa, $user, $tipo, $titulo, $mensagem, $link);
        }
    }
}
