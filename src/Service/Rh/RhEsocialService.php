<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhEsocialLote;
use App\Entity\User;
use App\Repository\RhEsocialLoteRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhEsocialService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhEsocialLoteRepository $repo,
        private RhAuditService $audit,
    ) {}

    /** @return list<RhEsocialLote> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findForEmpresa($empresa);
    }

    /**
     * Stub: cria lote eSocial pendente de envio.
     */
    public function createLote(Empresa $empresa, string $referencia, string $tipoEvento, ?User $actor = null): RhEsocialLote
    {
        $lote = new RhEsocialLote();
        $lote->setEmpresa($empresa);
        $lote->setReferencia($referencia);
        $lote->setTipoEvento($tipoEvento);
        $lote->setStatus('PENDENTE');
        $lote->setPayload(['stub' => true, 'message' => 'Envio real ao eSocial — fase futura.']);

        $this->em->persist($lote);
        $this->em->flush();

        $this->audit->log($empresa, $actor, 'esocial', 'criar_lote', 'rh_esocial_lote', $lote->getId());

        return $lote;
    }
}
