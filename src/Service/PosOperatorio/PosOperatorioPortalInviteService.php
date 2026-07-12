<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\PosOperatorioPacienteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PosOperatorioPortalInviteService
{
    private const SESSION_KEY = 'portal_invite_token';
    private const TTL_DAYS = 30;

    public function __construct(
        private PosOperatorioPacienteRepository $pacientes,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function generateInvite(PosOperatorioPaciente $paciente): string
    {
        $token = bin2hex(random_bytes(16));
        $paciente
            ->setPortalInviteToken($token)
            ->setPortalInviteExpiresAt(new \DateTimeImmutable('+' . self::TTL_DAYS . ' days'));
        $this->em->flush();

        return $this->buildInviteUrl($token);
    }

    public function buildInviteUrl(string $token): string
    {
        return $this->urlGenerator->generate(
            'app_portal_patient_invite',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    public function findValidPaciente(string $token): ?PosOperatorioPaciente
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $paciente = $this->pacientes->findOneBy(['portalInviteToken' => $token]);
        if (!$paciente instanceof PosOperatorioPaciente) {
            return null;
        }

        $expires = $paciente->getPortalInviteExpiresAt();
        if ($expires !== null && $expires < new \DateTimeImmutable()) {
            return null;
        }

        if ($paciente->getPortalUser() !== null) {
            return null;
        }

        return $paciente;
    }

    public function acceptInvite(PosOperatorioPaciente $paciente, User $user): bool
    {
        if ($paciente->getPortalUser() !== null) {
            return false;
        }

        $existing = $this->pacientes->findOneBy(['portalUser' => $user, 'empresa' => $paciente->getEmpresa()]);
        if ($existing instanceof PosOperatorioPaciente && $existing->getId() !== $paciente->getId()) {
            return false;
        }

        $paciente
            ->setPortalUser($user)
            ->setPortalInviteToken(null)
            ->setPortalInviteExpiresAt(null);
        $this->em->flush();

        return true;
    }

    public function sessionKey(): string
    {
        return self::SESSION_KEY;
    }
}
