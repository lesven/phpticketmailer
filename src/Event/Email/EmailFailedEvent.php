<?php
declare(strict_types=1);

namespace App\Event\Email;

use App\Event\AbstractDomainEvent;
use App\ValueObject\TicketData;
use App\ValueObject\EmailAddress;

/**
 * Event: E-Mail-Versand ist fehlgeschlagen
 * 
 * Wird ausgelöst, wenn beim Versenden einer Ticket-E-Mail ein Fehler
 * aufgetreten ist. Ermöglicht Fehlerbehandlung, Retry-Mechanismen
 * und Admin-Benachrichtigungen.
 */
final class EmailFailedEvent extends AbstractDomainEvent
{
    public function __construct(
        public readonly TicketData $ticketData,
        public readonly EmailAddress $email,
        public readonly string $subject,
        public readonly string $errorMessage,
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