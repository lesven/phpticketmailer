<?php

namespace App\ValueObject;

use App\Exception\InvalidTicketNumberException;

/**
 * Value Object für Ticket-Nummern
 * Stellt sicher, dass alle Ticket-Nummern das korrekte Format haben
 */
final readonly class TicketNumber
{
    private const PATTERN = '/^TICKET-\d+$/';
    private const MIN_NUMBER = 1;
    private const MAX_NUMBER = 999999;

    public function __construct(private string $value)
    {
        $this->validate($value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function generate(int $number): self
    {
        if ($number < self::MIN_NUMBER || $number > self::MAX_NUMBER) {
            throw new InvalidTicketNumberException(
                "Ticket number must be between " . self::MIN_NUMBER . " and " . self::MAX_NUMBER
            );
        }

        return new self(sprintf('TICKET-%06d', $number));
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getNumber(): int
    {
        return (int) substr($this->value, 7); // Remove "TICKET-" prefix
    }

    public function equals(TicketNumber $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validate(string $value): void
    {
        if (empty($value)) {
            throw new InvalidTicketNumberException('Ticket number cannot be empty');
        }

        if (!preg_match(self::PATTERN, $value)) {
            throw new InvalidTicketNumberException(
                "Invalid ticket number format: '{$value}'. Expected format: TICKET-123456"
            );
        }

        $number = $this->extractNumber($value);
        if ($number < self::MIN_NUMBER || $number > self::MAX_NUMBER) {
            throw new InvalidTicketNumberException(
                "Ticket number {$number} is out of valid range"
            );
        }
    }

    private function extractNumber(string $value): int
    {
        return (int) substr($value, 7);
    }
}