<?php

namespace App\Domain\Event;

/**
 * Contrato mínimo para eventos de domínio publicados na plataforma Unio.
 */
interface DomainEventInterface
{
    public function eventName(): string;

    public function module(): string;

    /** @return array<string, mixed> */
    public function payload(): array;

    public function occurredAt(): \DateTimeImmutable;
}
