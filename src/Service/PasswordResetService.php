<?php

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetService
{
    private const TOKEN_TTL_HOURS = 1;

    public function __construct(
        private UserRepository $userRepo,
        private PasswordResetTokenRepository $tokenRepo,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private TransactionalEmailComposer $emailComposer,
        private string $mailerFrom,
        private string $mailerDsn,
        private bool $kernelDebug,
    ) {}

    /**
     * @return string|null Link de reset (somente quando mailer não envia — ex.: null transport em dev)
     */
    public function requestReset(string $email): ?string
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return null;
        }

        $user = $this->userRepo->findOneBy(['email' => $email]);
        if (!$user instanceof User || !$user->isAtivo()) {
            return null;
        }

        $plainToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plainToken);

        $this->tokenRepo->invalidateActiveForUser($user);

        $token = new PasswordResetToken(
            $user,
            $hash,
            new \DateTimeImmutable('+' . self::TOKEN_TTL_HOURS . ' hours')
        );
        $this->em->persist($token);
        $this->em->flush();

        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $plainToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        if ($this->shouldExposeDevResetLink()) {
            return $resetUrl;
        }

        try {
            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFrom)
                    ->to($user->getEmail())
                    ->subject('Redefinição de senha — Unio')
                    ->text($this->buildEmailBody($user, $resetUrl))
            );
        } catch (\Throwable) {
            return $resetUrl;
        }

        return null;
    }

    private function shouldExposeDevResetLink(): bool
    {
        if (!$this->kernelDebug) {
            return false;
        }

        $dsn = strtolower($this->mailerDsn);

        return str_contains($dsn, 'null://') || str_contains($dsn, 'nulltransport');
    }

    public function resolveUserFromToken(string $plainToken): ?User
    {
        $token = $this->tokenRepo->findValidByHash(hash('sha256', $plainToken));

        return $token?->getUser();
    }

    public function consumeTokenAndResetPassword(
        string $plainToken,
        User $user,
        string $newPassword,
        UserPasswordHasherInterface $hasher,
    ): bool {
        $token = $this->tokenRepo->findValidByHash(hash('sha256', $plainToken));

        if (!$token || $token->getUser()->getId() !== $user->getId()) {
            return false;
        }

        $user->setPassword($hasher->hashPassword($user, $newPassword));
        $token->markUsed();
        $this->em->flush();

        return true;
    }

    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function buildEmailBody(User $user, string $resetUrl): string
    {
        $body = sprintf(
            "Olá, %s\n\nRecebemos uma solicitação para redefinir sua senha na Unio.\n\nAcesse o link abaixo (válido por %d hora):\n%s\n\nSe você não solicitou, ignore este e-mail.",
            $user->getNome(),
            self::TOKEN_TTL_HOURS,
            $resetUrl
        );

        return $this->emailComposer->appendPlainFooter($body);
    }
}
