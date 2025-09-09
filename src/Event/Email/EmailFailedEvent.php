<?php

namespace App\Event\Email;

use App\Event\AbstractDomainEvent;
use App\ValueObject\TicketId;
use App\ValueObject\Username;
use App\ValueObject\EmailAddress;
use App\ValueObject\TicketName;

/**
 * Event: E-Mail-Versand ist fehlgeschlagen
 * 
 * Wird ausgelöst, wenn beim Versenden einer Ticket-E-Mail ein Fehler
 * aufgetreten ist. Ermöglicht Fehlerbehandlung, Retry-Mechanismen
 * und Admin-Benachrichtigungen.
 */
class EmailFailedEvent extends AbstractDomainEvent
{
    public function __construct(
        public readonly TicketId $ticketId,
        public readonly Username $username,
        public readonly EmailAddress $email,
        public readonly string $subject,
        public readonly string $errorMessage,
        public readonly bool $testMode = false,
        public readonly ?TicketName $ticketName = null
    ) {
        parent::__construct();
    }
}