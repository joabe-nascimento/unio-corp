<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhAuditLog;
use App\Entity\User;
use App\Repository\RhAuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhAuditService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhAuditLogRepository $repo,
    ) {}

    public function log(
        Empresa $empresa,
        ?User $user,
        string $modulo,
        string $acao,
        ?string $entidade = null,
        ?int $entidadeId = null,
        ?array $payload = null,
    ): RhAuditLog {
        $entry = new RhAuditLog();
        $entry->setEmpresa($empresa);
        $entry->setUser($user);
        $entry->setModulo($modulo);
        $entry->setAcao($acao);
        $entry->setEntidade($entidade);
        $entry->setEntidadeId($entidadeId);
        $entry->setPayload($payload);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /** @return list<RhAuditLog> */
    public function listForEmpresa(Empresa $empresa, ?string $modulo = null): array
    {
        return $this->repo->findForEmpresa($empresa, $modulo);
    }
}
