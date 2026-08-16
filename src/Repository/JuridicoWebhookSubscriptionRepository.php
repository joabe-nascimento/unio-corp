<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoWebhookSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoWebhookSubscription> */
class JuridicoWebhookSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoWebhookSubscription::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoWebhookSubscription
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoWebhookSubscription> */
    public function findAtivasForEvento(Empresa $empresa, string $evento): array
    {
        $todas = $this->findBy(['empresa' => $empresa, 'ativo' => true]);

        return array_values(array_filter(
            $todas,
            static fn (JuridicoWebhookSubscription $s) => \in_array($evento, $s->getEventos(), true) || \in_array('*', $s->getEventos(), true),
        ));
    }
}
