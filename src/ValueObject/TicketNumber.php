<?php
declare(strict_types=1);
/**
 * TicketNumber.php
 *
 * Diese Value Object-Klasse repräsentiert eine gültige Ticket-Nummer.
 * Sie stellt sicher, dass alle Ticket-Nummern das korrekte Format (TICKET-123456)
 * haben und innerhalb des gültigen Zahlenbereichs liegen.
 *
 * @package App\ValueObject
 */

namespace App\ValueObject;

use App\Exception\InvalidTicketNumberException;

/**
 * Value Object für Ticket-Nummern
 *
 * Kapselt die Logik zur Validierung und Verarbeitung von Ticket-Nummern.
 * Ticket-Nummern müssen dem Format TICKET-NNNNNN entsprechen, wobei
 * NNNNNN eine 6-stellige Zahl zwischen 1 und 999999 ist.
 */
final readonly class TicketNumber
{
    /**
     * Regex-Pattern für gültige Ticket-Nummern
     */
    private const PATTERN = '/^TICKET-\d+$/';
    
    /**
     * Minimale erlaubte Ticket-Nummer
     */
    private const MIN_NUMBER = 1;
    
    /**
     * Maximale erlaubte Ticket-Nummer
     */
    private const MAX_NUMBER = 999999;

    /**
     * Konstruktor
     *
     * @param string $value Die Ticket-Nummer als String
     * @throws InvalidTicketNumberException Bei ungültiger Ticket-Nummer
     */
    public function __construct(private string $value)
    {
        $this->validate($value);
    }

    /**
     * Erstellt eine TicketNumber-Instanz aus einem String
     *
     * @param string $value Die Ticket-Nummer als String
     * @return self Eine neue TicketNumber-Instanz
     * @throws InvalidTicketNumberException Bei ungültiger Ticket-Nummer
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Generiert eine Ticket-Nummer aus einer Zahl
     *
     * Diese Methode erstellt eine formatierte Ticket-Nummer (TICKET-123456)
     * aus einer gegebenen Zahl mit führenden Nullen.
     *
     * @param int $number Die Ticket-Nummer als Integer
     * @return self Eine neue TicketNumber-Instanz
     * @throws InvalidTicketNumberException Bei ungültiger Nummer
     */
    public static function generate(int $number): self
    {
        if ($number < self::MIN_NUMBER || $number > self::MAX_NUMBER) {
            throw new InvalidTicketNumberException(
                "Ticket number must be between " . self::MIN_NUMBER . " and " . self::MAX_NUMBER
            );
        }

        return new self(sprintf('TICKET-%06d', $number));
    }

    /**
     * Gibt den String-Wert der Ticket-Nummer zurück
     *
     * @return string Die vollständige Ticket-Nummer (z.B. "TICKET-123456")
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Extrahiert die numerische Ticket-Nummer
     *
     * @return int Die numerische Ticket-Nummer ohne Präfix
     */
    public function getNumber(): int
    {
        return (int) substr($this->value, 7); // Remove "TICKET-" prefix
    }

    /**
     * Vergleicht diese Ticket-Nummer mit einer anderen
     *
     * @param TicketNumber $other Die zu vergleichende Ticket-Nummer
     * @return bool True wenn beide Ticket-Nummern identisch sind
     */
    public function equals(TicketNumber $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * String-Repräsentation der Ticket-Nummer
     *
     * @return string Die Ticket-Nummer als String
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Validiert die Ticket-Nummer auf Format und Zahlenbereich
     *
     * @param string $value Die zu validierende Ticket-Nummer
     * @return void
     * @throws InvalidTicketNumberException Bei ungültiger Ticket-Nummer
     */
    private function validate(string $value): void
    {
        if ($value === '') {
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

    /**
     * Extrahiert die Zahl aus der Ticket-Nummer
     *
     * @param string $value Die vollständige Ticket-Nummer
     * @return int Die numerische Ticket-Nummer
     */
    private function extractNumber(string $value): int
    {
        return (int) substr($value, 7);
    }
}