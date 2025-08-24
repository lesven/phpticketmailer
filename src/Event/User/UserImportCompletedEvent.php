<?php

namespace App\Event\User;

use App\Event\AbstractDomainEvent;

/**
 * Event: User Import wurde abgeschlossen
 * 
 * Wird ausgelöst, wenn ein CSV-Import vollständig abgeschlossen ist.
 * Enthält zusammenfassende Statistiken über den Import-Vorgang.
 * Ermöglicht finale Aktionen wie Berichte, Cleanup oder Admin-Benachrichtigungen.
 */
class UserImportCompletedEvent extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $successCount,
        public readonly int $errorCount,
        public readonly array $errors,
        public readonly string $filename,
        public readonly float $durationInSeconds
    ) {
        parent::__construct();
    }

    /**
     * Gesamtanzahl der verarbeiteten Datensätze
     */
    public function getTotalProcessed(): int
    {
        return $this->successCount + $this->errorCount;
    }

    /**
     * Erfolgsrate in Prozent
     */
    public function getSuccessRate(): float
    {
        $total = $this->getTotalProcessed();
        if ($total === 0) {
            return 0.0;
        }
        
        return ($this->successCount / $total) * 100;
    }

    /**
     * Ob der Import erfolgreich war (keine Fehler)
     */
    public function wasSuccessful(): bool
    {
        return $this->errorCount === 0;
    }
}