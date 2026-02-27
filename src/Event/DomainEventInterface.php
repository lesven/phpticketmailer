<?php
declare(strict_types=1);

namespace App\Event;

/**
 * Marker Interface für Domain Events
 * 
 * Domain Events repräsentieren bedeutsame Geschäftsereignisse,
 * die in der Domäne aufgetreten sind.
 */
interface DomainEventInterface
{
    /**
     * Zeitpunkt des Ereignisses
     */
    public function getOccurredAt(): \DateTimeImmutable;
}