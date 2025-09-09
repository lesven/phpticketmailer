<?php

namespace App\Event\Email;

use App\Event\AbstractDomainEvent;
use App\ValueObject\TicketId;
use App\ValueObject\Username;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketName;

/**
 * Event: Eine E-Mail wurde übersprungen
 * 
 * Wird ausgelöst, wenn eine Ticket-E-Mail nicht versendet wurde, weil:
 * - Das Ticket bereits verarbeitet wurde
 * - Es sich um ein Duplikat in der CSV handelt
 * - Der Benutzer von Umfragen ausgeschlossen ist
 * - Kein Benutzer/E-Mail gefunden wurde
 * 
 * Ermöglicht Audit-Logging, Statistiken und Follow-up-Aktionen.
 */
class EmailSkippedEvent extends AbstractDomainEvent
{
    public function __construct(
        public readonly TicketId $ticketId,
        public readonly Username $username,
        public readonly ?EmailAddress $email,
        public readonly EmailStatus $status,
        public readonly bool $testMode = false,
        public readonly ?TicketName $ticketName = null
    ) {
        parent::__construct();
    }
}
