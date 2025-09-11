<?php

namespace App\Event\Email;

use App\Event\AbstractDomainEvent;
use App\ValueObject\TicketData;
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
        public readonly TicketData $ticketData,
        public readonly EmailAddress $email,
        public readonly string $subject,
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