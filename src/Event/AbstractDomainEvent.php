<?php
declare(strict_types=1);

namespace App\Event;

/**
 * Basis-Implementierung für Domain Events
 * 
 * Stellt gemeinsame Funktionalität für alle Domain Events bereit,
 * insbesondere den Zeitstempel des Ereignisses.
 */
abstract class AbstractDomainEvent implements DomainEventInterface
{
    protected readonly \DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}