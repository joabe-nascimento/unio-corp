<?php

namespace App\Repository;

use App\Entity\ClinicOutboundMessage;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicOutboundMessage> */
class ClinicOutboundMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicOutboundMessage::class);
    }

    /** @return list<ClinicOutboundMessage> */
    public function findRecentByEmpresa(Empresa $empresa, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('m.criadoEm', 'DESC')
            ->setMaxResults(max(1, min(100, $limit)))
            ->getQuery()
            ->getResult();
    }

    public function save(ClinicOutboundMessage $message, bool $flush = true): void
    {
        $this->getEntityManager()->persist($message);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
