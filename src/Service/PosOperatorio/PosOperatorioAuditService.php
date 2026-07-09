<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class PosOperatorioAuditService
{
    public function __construct(
        private PosOperatorioEventRecorder $events,
        private EntityManagerInterface $em,
    ) {}

    public function logAccess(PosOperatorioPaciente $paciente, User $user, string $contexto, ?Request $request = null): void
    {
        $quem = $user->getNome() ?? $user->getEmail() ?? 'Equipe';
        $this->events->record(
            $paciente,
            PosOperatorioEvento::TIPO_ACESSO_FICHA,
            sprintf('Ficha visualizada por %s', $quem),
            $user,
        );
        $this->em->flush();
    }

    public function logConsent(PosOperatorioPaciente $paciente, User $user, ?Request $request = null): void
    {
        $paciente->setConsentimentoLgpdEm(new \DateTimeImmutable());
        $ip = $request?->getClientIp() ?? '—';
        $this->events->record(
            $paciente,
            PosOperatorioEvento::TIPO_CONSENTIMENTO,
            sprintf('Consentimento LGPD registrado · IP %s', $ip),
            $user,
        );
        $this->em->flush();
    }

    public function hasConsent(PosOperatorioPaciente $paciente): bool
    {
        return $paciente->getConsentimentoLgpdEm() !== null;
    }
}
