<?php

namespace App\Event\Email;

use App\Event\AbstractDomainEvent;

/**
 * Event: Bulk E-Mail-Versand wurde abgeschlossen
 * 
 * Wird ausgelöst, wenn ein gesamter Batch von Ticket-E-Mails
 * verarbeitet wurde. Enthält Statistiken über den gesamten Vorgang.
 */
class BulkEmailCompletedEvent extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $totalEmails,
        public readonly int $sentCount,
        public readonly int $failedCount,
        public readonly int $skippedCount,
        public readonly bool $testMode,
        public readonly float $durationInSeconds
    ) {
        parent::__construct();
    }

    /**
     * Erfolgsrate in Prozent
     */
    public function getSuccessRate(): float
    {
        if ($this->totalEmails === 0) {
            return 0.0;
        }
        
        return ($this->sentCount / $this->totalEmails) * 100;
    }

    /**
     * Ob der Bulk-Versand erfolgreich war (keine Fehler)
     */
    public function wasSuccessful(): bool
    {
        return $this->failedCount === 0;
    }
}