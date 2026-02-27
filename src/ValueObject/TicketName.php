<?php
declare(strict_types=1);

namespace App\ValueObject;

use App\Exception\InvalidTicketNameException;

/**
 * Value Object für Ticket-Namen
 *
 * Stellt sicher, dass Ticket-Namen getrimmt werden und eine maximale
 * Länge von 50 Zeichen nicht überschreiten.
 */
final readonly class TicketName
{
    private const MAX_LENGTH = 50;

    public function __construct(private string $value)
    {
        $this->validate($value);
    }

    /**
     * Erzeugt eine TicketName-Instanz aus einem String.
     */
    public static function fromString(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidTicketNameException('Ticket name cannot be empty');
        }
        if (mb_strlen($name) > self::MAX_LENGTH) {
            $name = mb_substr($name, 0, self::MAX_LENGTH);
        }
        return new self($name);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(TicketName $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validate(string $value): void
    {
        if ($value === '') {
            throw new InvalidTicketNameException('Ticket name cannot be empty');
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidTicketNameException('Ticket name exceeds maximum length of '.self::MAX_LENGTH.' characters');
        }
    }
}
