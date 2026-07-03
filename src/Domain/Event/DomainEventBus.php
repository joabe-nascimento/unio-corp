<?php

namespace App\Domain\Event;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Espinha dorsal de eventos — publica mensagens de domínio no Messenger.
 */
final class DomainEventBus
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {}

    public function publish(DomainEventInterface $event): void
    {
        $this->bus->dispatch($event);
    }
}
