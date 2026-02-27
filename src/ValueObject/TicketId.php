<?php
declare(strict_types=1);

namespace App\ValueObject;

use App\Exception\InvalidTicketIdException;

/**
 * Value Object für Ticket-IDs
 * 
 * Diese immutable Klasse kapselt die Validierung und Handhabung von Ticket-IDs.
 * Sie folgt Domain-Driven Design Prinzipien und stellt sicher, dass nur
 * gültige Ticket-IDs erstellt werden können.
 * 
 * Features:
 * - Format-Validierung (Alphanumerisch + Bindestriche)
 * - Längen-Validierung (3-50 Zeichen)
 * - Normalisierung (Trimming, Case-Handling)
 * - Typ-Sicherheit
 * 
 * @author Generated with Claude Code
 * @since 1.0.0
 */
final readonly class TicketId
{
    /** @var int Minimale Länge einer Ticket-ID */
    private const MIN_LENGTH = 3;
    
    /** @var int Maximale Länge einer Ticket-ID */
    private const MAX_LENGTH = 50;
    
    /** @var string Erlaubte Zeichen in Ticket-IDs (Regex-Pattern) */
    private const VALID_PATTERN = '/^[A-Z0-9._-]+$/i';

    /**
     * Konstruktor für TicketId
     * 
     * @param string $value Die validierte Ticket-ID
     */
    public function __construct(private string $value)
    {
        if (empty($value)) {
            throw new InvalidTicketIdException('Ticket ID cannot be empty');
        }
    }

    /**
     * Erstellt eine TicketId Instanz aus einem String
     * 
     * Validiert und normalisiert die Ticket-ID.
     * 
     * @param string $ticketId Die rohe Ticket-ID
     * @return self Eine neue TicketId Instanz
     * @throws InvalidTicketIdException Wenn die Ticket-ID ungültig ist
     */
    public static function fromString(string $ticketId): self
    {
        $normalized = self::normalize($ticketId);
        self::validate($normalized);
        
        return new self($normalized);
    }

    /**
     * Gibt den String-Wert der Ticket-ID zurück
     * 
     * @return string Die Ticket-ID als String
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * String-Repräsentation der Ticket-ID
     * 
     * @return string Die Ticket-ID als String
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Vergleicht zwei TicketId Instanzen auf Gleichheit
     * 
     * @param TicketId $other Die andere TicketId Instanz
     * @return bool True wenn beide Ticket-IDs identisch sind
     */
    public function equals(TicketId $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Prüft ob die Ticket-ID ein bestimmtes Präfix hat
     * 
     * @param string $prefix Das zu prüfende Präfix
     * @return bool True wenn die Ticket-ID mit dem Präfix beginnt
     */
    public function hasPrefix(string $prefix): bool
    {
        return str_starts_with(strtoupper($this->value), strtoupper($prefix));
    }

    /**
     * Extrahiert das Präfix der Ticket-ID (bis zum ersten Trennzeichen)
     * 
     * @return string Das Präfix oder die komplette ID wenn kein Trennzeichen
     */
    public function getPrefix(): string
    {
        $separators = ['-', '_', '.'];
        
        foreach ($separators as $separator) {
            $pos = strpos($this->value, $separator);
            if ($pos !== false) {
                return substr($this->value, 0, $pos);
            }
        }
        
        return $this->value;
    }

    /**
     * Normalisiert eine Ticket-ID
     * 
     * - Entfernt führende und nachstehende Leerzeichen
     * - Konvertiert zu Großbuchstaben für Konsistenz (optional)
     * 
     * @param string $ticketId Die rohe Ticket-ID
     * @return string Die normalisierte Ticket-ID
     */
    private static function normalize(string $ticketId): string
    {
        // Optional: Konvertierung zu Großbuchstaben für Konsistenz
        // Auskommentiert, da manche Systeme case-sensitive sein könnten
        // return strtoupper(trim($ticketId));
        return trim($ticketId);
    }

    /**
     * Validiert eine normalisierte Ticket-ID
     * 
     * Prüft:
     * - Länge (MIN_LENGTH bis MAX_LENGTH)
     * - Erlaubte Zeichen (Alphanumerisch + Bindestriche, Unterstriche, Punkte)
     * - Keine aufeinanderfolgenden Trennzeichen
     * 
     * @param string $ticketId Die zu validierende Ticket-ID
    /** @throws InvalidTicketIdException */
    private static function validate(string $ticketId): void
    {
        if ($ticketId === '') {
            throw new InvalidTicketIdException('Ticket ID cannot be empty');
        }

        self::validateTicketIdLength($ticketId);
        self::validateTicketIdPattern($ticketId);
    }

    /** @throws InvalidTicketIdException */
    private static function validateTicketIdLength(string $ticketId): void
    {
        $length = strlen($ticketId);
        if ($length < self::MIN_LENGTH) {
            throw new InvalidTicketIdException(
                "Ticket ID must be at least " . self::MIN_LENGTH . " characters long, got {$length}"
            );
        }

        if ($length > self::MAX_LENGTH) {
            throw new InvalidTicketIdException(
                "Ticket ID must not exceed " . self::MAX_LENGTH . " characters, got {$length}"
            );
        }
    }

    /** @throws InvalidTicketIdException */
    private static function validateTicketIdPattern(string $ticketId): void
    {
        if (! preg_match(self::VALID_PATTERN, $ticketId)) {
            throw new InvalidTicketIdException(
                'Ticket ID contains invalid characters. Only letters, numbers, dots, hyphens and underscores are allowed'
            );
        }

        if (preg_match('/[-_.]{2,}/', $ticketId)) {
            throw new InvalidTicketIdException('Ticket ID cannot contain consecutive separators (-, _, .)');
        }

        if (preg_match('/^[-_.]|[-_.]$/', $ticketId)) {
            throw new InvalidTicketIdException('Ticket ID cannot start or end with separators (-, _, .)');
        }
    }
}