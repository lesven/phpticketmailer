<?php

namespace App\ValueObject;

/**
 * Value Object für einen unbekannten Benutzer mit zugehörigen Ticket-Informationen
 * 
 * Wird verwendet um unbekannte Benutzer mit Kontext anzuzeigen, damit Nutzer
 * besser nachvollziehen können, zu welchem Ticket die E-Mail-Zuordnung gehört.
 */
final readonly class UnknownUserWithTicket
{
    public function __construct(
        public Username $username,
        public TicketId $ticketId,
        public ?TicketName $ticketName = null
    ) {
    }

    /**
     * Convenience factory from TicketData
     */
    public static function fromTicketData(TicketData $ticketData): self
    {
        return new self(
            $ticketData->username,
            $ticketData->ticketId,
            $ticketData->ticketName
        );
    }

    /**
     * Gibt den Benutzernamen als String zurück
     */
    public function getUsernameString(): string
    {
        return $this->username->getValue();
    }

    /**
     * Gibt die Ticket-ID als String zurück
     */
    public function getTicketIdString(): string
    {
        return $this->ticketId->getValue();
    }

    /**
     * Gibt den Ticket-Namen als String zurück oder null
     */
    public function getTicketNameString(): ?string
    {
        return $this->ticketName?->getValue();
    }
}