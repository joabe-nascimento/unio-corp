<?php

namespace App\Service\Juridico;

use App\Entity\JuridicoCliente;
use App\Entity\User;
use App\Repository\JuridicoClienteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class JuridicoPortalInviteService
{
    private const SESSION_KEY = 'juridico_portal_invite_token';
    private const TTL_DAYS = 30;

    public function __construct(
        private JuridicoClienteRepository $clienteRepo,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function generateInvite(JuridicoCliente $cliente): string
    {
        $token = bin2hex(random_bytes(16));
        $cliente
            ->setPortalInviteToken($token)
            ->setPortalInviteExpiresAt(new \DateTimeImmutable('+' . self::TTL_DAYS . ' days'))
            ->touch();
        $this->em->flush();

        return $this->buildInviteUrl($token);
    }

    public function buildInviteUrl(string $token): string
    {
        return $this->urlGenerator->generate(
            'app_juridico_portal_convite',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    public function findValidCliente(string $token): ?JuridicoCliente
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $cliente = $this->clienteRepo->findOneBy(['portalInviteToken' => $token]);
        if (!$cliente instanceof JuridicoCliente) {
            return null;
        }

        $expires = $cliente->getPortalInviteExpiresAt();
        if ($expires !== null && $expires < new \DateTimeImmutable()) {
            return null;
        }

        if ($cliente->hasPortalAtivo()) {
            return null;
        }

        return $cliente;
    }

    public function acceptInvite(JuridicoCliente $cliente, User $user): bool
    {
        if ($cliente->hasPortalAtivo()) {
            return false;
        }

        $existing = $this->clienteRepo->findOneBy(['portalUser' => $user, 'empresa' => $cliente->getEmpresa()]);
        if ($existing instanceof JuridicoCliente && $existing->getId() !== $cliente->getId()) {
            return false;
        }

        $cliente
            ->setPortalUser($user)
            ->setPortalInviteToken(null)
            ->setPortalInviteExpiresAt(null)
            ->touch();
        $this->em->flush();

        return true;
    }

    public function sessionKey(): string
    {
        return self::SESSION_KEY;
    }
}
