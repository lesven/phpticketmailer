<?php

namespace App\Event\Email;

use App\Event\AbstractDomainEvent;
use App\ValueObject\TicketId;
use App\ValueObject\Username;
use App\ValueObject\EmailAddress;

/**
 * Event: Eine E-Mail wurde erfolgreich versendet
 * 
 * Wird ausgelöst, wenn eine Ticket-E-Mail erfolgreich an einen Benutzer
 * versendet wurde. Ermöglicht Audit-Logging, Statistiken und Follow-up-Aktionen.
 */
class EmailSentEvent extends AbstractDomainEvent
{
    public function __construct(
        public readonly TicketId $ticketId,
        public readonly Username $username,
        public readonly EmailAddress $email,
        public readonly string $subject,
        public readonly bool $testMode = false,
        public readonly ?string $ticketName = null
    ) {
        parent::__construct();
    }
}