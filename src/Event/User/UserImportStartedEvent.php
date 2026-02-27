<?php
declare(strict_types=1);

namespace App\Event\User;

use App\Event\AbstractDomainEvent;

/**
 * Event: User Import wurde gestartet
 * 
 * Wird ausgelöst, wenn ein CSV-Import von Benutzern begonnen wird.
 * Ermöglicht es anderen Komponenten, auf den Import-Start zu reagieren
 * (z.B. Logging, Progress Tracking, Notifications).
 */
final class UserImportStartedEvent extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $totalRows,
        public readonly string $filename,
        public readonly bool $clearExisting = false
    ) {
        parent::__construct();
    }
}