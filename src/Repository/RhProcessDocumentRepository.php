<?php

namespace App\Repository;

use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Entity\RhProcessDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RhProcessDocument> */
class RhProcessDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RhProcessDocument::class);
    }

    /** @return list<RhProcessDocument> */
    public function findByOnboarding(RhOnboardingProcess $process): array
    {
        return $this->findBy(['onboarding' => $process], ['criadoEm' => 'DESC']);
    }

    /** @return list<RhProcessDocument> */
    public function findByOffboarding(RhOffboardingProcess $process): array
    {
        return $this->findBy(['offboarding' => $process], ['criadoEm' => 'DESC']);
    }
}
