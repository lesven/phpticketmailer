<?php
/**
 * EmailStatus.php
 * 
 * Value Object für E-Mail-Status mit automatischer Längenvalidierung.
 * Stellt sicher, dass Status-Texte nicht länger als 50 Zeichen sind.
 * 
 * @package App\ValueObject
 */

namespace App\ValueObject;

/**
 * EmailStatus Value Object
 * 
 * Repräsentiert einen E-Mail-Status mit automatischer Validierung der maximalen Länge.
 */
final class EmailStatus
{
    private const MAX_LENGTH = 50;
    
    private string $value;

    /**
     * Erstellt einen neuen EmailStatus
     * 
     * @param string $value Der Status-Text
     * @throws \InvalidArgumentException Wenn der Status zu lang oder leer ist
     */
    public function __construct(string $value)
    {
        $trimmedValue = trim($value);
        
        if (empty($trimmedValue)) {
            throw new \InvalidArgumentException('Status darf nicht leer sein');
        }
        
        if (strlen($trimmedValue) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Status darf maximal %d Zeichen haben, %d gegeben: "%s"',
                    self::MAX_LENGTH,
                    strlen($trimmedValue),
                    $trimmedValue
                )
            );
        }
        
        $this->value = $trimmedValue;
    }

    /**
     * Erstellt einen EmailStatus aus einem String
     * 
     * @param string $value Der Status-Text
     * @return self
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Erstellt einen Status für bereits verarbeitete Tickets
     * 
     * @param \DateTimeInterface $date Das Verarbeitungsdatum
     * @return self
     */
    public static function alreadyProcessed(\DateTimeInterface $date): self
    {
        return new self('Bereits verarbeitet am ' . $date->format('d.m.Y'));
    }

    /**
     * Erstellt einen Status für Duplikate in CSV
     * 
     * @return self
     */
    public static function duplicateInCsv(): self
    {
        return new self('Nicht versendet – Mehrfach in CSV');
    }

    /**
     * Erstellt einen Status für von Umfragen ausgeschlossene Tickets
     * 
     * @return self
     */
    public static function excludedFromSurvey(): self
    {
        return new self('Nicht versendet – Von Umfragen ausgeschlossen');
    }

    /**
     * Erstellt einen Status für erfolgreich versendete E-Mails
     * 
     * @return self
     */
    public static function sent(): self
    {
        return new self('Versendet');
    }

    /**
     * Erstellt einen Status für Fehler beim Versenden
     * 
     * @param string $errorMessage Die Fehlermeldung (wird gekürzt falls nötig)
     * @return self
     */
    public static function error(string $errorMessage): self
    {
        $prefix = 'Fehler: ';
        $maxErrorLength = self::MAX_LENGTH - strlen($prefix);
        
        if (strlen($errorMessage) > $maxErrorLength) {
            $errorMessage = substr($errorMessage, 0, $maxErrorLength - 3) . '...';
        }
        
        return new self($prefix . $errorMessage);
    }

    /**
     * Gibt den Status-Text zurück
     * 
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * String-Repräsentation des Status
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Prüft auf Gleichheit mit einem anderen EmailStatus
     * 
     * @param EmailStatus $other
     * @return bool
     */
    public function equals(EmailStatus $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Prüft, ob der Status einen erfolgreichen Versand anzeigt
     * 
     * @return bool True, wenn die E-Mail erfolgreich versendet wurde
     */
    public function isSent(): bool
    {
        return str_starts_with($this->value, 'Versendet');
    }

    /**
     * Prüft, ob der Status einen Fehler anzeigt
     * 
     * @return bool True, wenn beim Versand ein Fehler aufgetreten ist
     */
    public function isError(): bool
    {
        return str_starts_with($this->value, 'Fehler:');
    }

    /**
     * Prüft, ob der Status anzeigt, dass die E-Mail bereits verarbeitet wurde
     * 
     * @return bool True, wenn die E-Mail bereits verarbeitet wurde
     */
    public function isAlreadyProcessed(): bool
    {
        return str_starts_with($this->value, 'Bereits verarbeitet');
    }

    /**
     * Prüft, ob der Status anzeigt, dass die E-Mail aufgrund eines Duplikats übersprungen wurde
     * 
     * @return bool True, wenn die E-Mail wegen Duplikat übersprungen wurde
     */
    public function isDuplicate(): bool
    {
        return str_starts_with($this->value, 'Nicht versendet – Mehrfach in CSV');
    }

    /**
     * Prüft, ob der Status anzeigt, dass die E-Mail aufgrund von Umfrage-Ausschluss übersprungen wurde
     * 
     * @return bool True, wenn die E-Mail wegen Umfrage-Ausschluss übersprungen wurde
     */
    public function isExcludedFromSurvey(): bool
    {
        return str_starts_with($this->value, 'Nicht versendet – Von Umfragen ausgeschlossen');
    }

    /**
     * Prüft, ob der Status anzeigt, dass die E-Mail übersprungen wurde (alle Skip-Szenarien)
     * 
     * @return bool True, wenn die E-Mail aus irgendeinem Grund übersprungen wurde
     */
    public function isSkipped(): bool
    {
        return $this->isAlreadyProcessed() 
            || $this->isDuplicate() 
            || $this->isExcludedFromSurvey()
            || str_starts_with($this->value, 'Nicht versendet');
    }
}
