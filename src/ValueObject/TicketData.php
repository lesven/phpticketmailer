<?php
declare(strict_types=1);

namespace App\ValueObject;

/**
 * Aggregates ticket related data as value object.
 */
final readonly class TicketData
{
    public function __construct(
        public TicketId $ticketId,
        public Username $username,
        public ?TicketName $ticketName = null,
        public ?string $created = null
    ) {
    }

    /**
     * Convenience factory from raw strings.
     */
    public static function fromStrings(string $ticketId, string $username, ?string $ticketName = null, ?string $created = null): self
    {
        return new self(
            TicketId::fromString($ticketId),
            Username::fromString($username),
            $ticketName !== null && trim($ticketName) !== '' ? TicketName::fromString($ticketName) : null,
            $created !== null && trim($created) !== '' ? trim($created) : null
        );
    }
}
