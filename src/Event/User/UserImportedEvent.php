<?php

namespace App\Event\User;

use App\Event\AbstractDomainEvent;
use App\ValueObject\Username;
use App\ValueObject\EmailAddress;

/**
 * Event: Ein User wurde erfolgreich importiert
 * 
 * Wird für jeden erfolgreich importierten Benutzer ausgelöst.
 * Ermöglicht granulare Reaktionen auf einzelne Import-Erfolge
 * (z.B. individuelle Benachrichtigungen, detaillierte Audit-Logs).
 */
class UserImportedEvent extends AbstractDomainEvent
{
    public function __construct(
        public readonly Username $username,
        public readonly EmailAddress $email,
        public readonly bool $excludedFromSurveys = false
    ) {
        parent::__construct();
    }
}