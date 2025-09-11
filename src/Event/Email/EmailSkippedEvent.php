<?php

namespace App\Event\Email;

use App\Event\AbstractDomainEvent;
use App\ValueObject\TicketData;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;

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
        public readonly TicketData $ticketData,
        public readonly ?EmailAddress $email,
        public readonly EmailStatus $status,
        public readonly bool $testMode = false
    ) {
        parent::__construct();
    }
    
    // Convenience getters for backward compatibility (können später entfernt werden)
    public function getTicketId(): \App\ValueObject\TicketId
    {
        return $this->ticketData->ticketId;
    }
    
    public function getUsername(): \App\ValueObject\Username
    {
        return $this->ticketData->username;
    }
    
    public function getTicketName(): ?\App\ValueObject\TicketName
    {
        return $this->ticketData->ticketName;
    }
}
