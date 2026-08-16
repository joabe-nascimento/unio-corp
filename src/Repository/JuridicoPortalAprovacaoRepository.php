<?php

namespace App\Repository;

use App\Entity\JuridicoCliente;
use App\Entity\JuridicoPortalAprovacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoPortalAprovacao> */
class JuridicoPortalAprovacaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoPortalAprovacao::class);
    }

    /** @return list<JuridicoPortalAprovacao> */
    public function findForCliente(JuridicoCliente $cliente): array
    {
        return $this->findBy(['cliente' => $cliente], ['criadoEm' => 'DESC']);
    }
}
