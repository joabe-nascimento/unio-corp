<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhAssinaturaEnvelope;
use App\Entity\User;
use App\Repository\RhAssinaturaEnvelopeRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhAssinaturaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhAssinaturaEnvelopeRepository $repo,
        private RhAuditService $audit,
    ) {}

    /** @return list<RhAssinaturaEnvelope> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findForEmpresa($empresa);
    }

    /**
     * Stub: cria envelope de assinatura digital.
     */
    public function createEnvelope(Empresa $empresa, string $titulo, ?User $actor = null): RhAssinaturaEnvelope
    {
        $envelope = new RhAssinaturaEnvelope();
        $envelope->setEmpresa($empresa);
        $envelope->setTitulo(trim($titulo));
        $envelope->setStatus(RhAssinaturaEnvelope::STATUS_PENDENTE);

        $this->em->persist($envelope);
        $this->em->flush();

        $this->audit->log($empresa, $actor, 'assinatura', 'criar_envelope', 'rh_assinatura_envelope', $envelope->getId());

        return $envelope;
    }
}
