<?php

namespace App\ValueObject;

use App\Exception\InvalidEmailAddressException;

/**
 * Value Object für E-Mail-Adressen
 * 
 * Diese immutable Klasse stellt sicher, dass alle E-Mail-Adressen gültig,
 * normalisiert und sicher sind. Sie implementiert umfassende Validierung
 * nach RFC-Standards und bietet zusätzliche Sicherheitsfeatures.
 * 
 * Features:
 * - RFC 5321 konforme Validierung
 * - Automatische Normalisierung (Kleinschreibung, Leerzeichen entfernen)
 * - Blockierung von Wegwerf-E-Mail-Anbietern
 * - Domain-Validierung mit detaillierter Prüfung
 * - Business vs. Private E-Mail Erkennung
 * - Extraktion von Local-Part und Domain
 * 
 * @author Ihr Name
 * @since 1.0.0
 */
final readonly class EmailAddress
{
    /** @var int Maximale Länge einer E-Mail-Adresse nach RFC 5321 */
    private const MAX_LENGTH = 320;
    
    /** 
     * Liste blockierter Wegwerf-E-Mail-Domains
     * Diese Domains werden automatisch abgelehnt
     * @var array<string>
     */
    private const BLOCKED_DOMAINS = ['temp-mail.org', '10minutemail.com', 'guerrillamail.com'];

    /**
     * Konstruktor für EmailAddress
     * 
     * @param string $value Die bereits validierte und normalisierte E-Mail-Adresse
     * @throws InvalidEmailAddressException Wenn die E-Mail-Adresse ungültig ist
     */
    public function __construct(private string $value)
    {
        $this->validate($value);
    }

    /**
     * Erstellt eine EmailAddress Instanz aus einem String
     * 
     * Die E-Mail-Adresse wird automatisch normalisiert (Kleinschreibung,
     * Leerzeichen entfernt, mehrfache Punkte korrigiert).
     * 
     * @param string $email Die E-Mail-Adresse als String
     * @return self Eine neue EmailAddress Instanz
     * @throws InvalidEmailAddressException Wenn die E-Mail-Adresse ungültig ist
     */
    public static function fromString(string $email): self
    {
        return new self(self::normalize($email));
    }

    /**
     * Gibt die E-Mail-Adresse als String zurück
     * 
     * @return string Die normalisierte E-Mail-Adresse
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Extrahiert den Local-Part (Teil vor dem @) der E-Mail-Adresse
     * 
     * Beispiel: Für "user@example.com" wird "user" zurückgegeben
     * 
     * @return string Der Local-Part der E-Mail-Adresse
     */
    public function getLocalPart(): string
    {
        return substr($this->value, 0, strrpos($this->value, '@'));
    }

    /**
     * Extrahiert die Domain (Teil nach dem @) der E-Mail-Adresse
     * 
     * Beispiel: Für "user@example.com" wird "example.com" zurückgegeben
     * 
     * @return string Die Domain der E-Mail-Adresse
     */
    public function getDomain(): string
    {
        return substr($this->value, strrpos($this->value, '@') + 1);
    }

    /**
     * Prüft ob es sich um eine Business-E-Mail handelt
     * 
     * Business-E-Mails sind solche, die NICHT von bekannten
     * kostenlosen Anbietern stammen (Gmail, Yahoo, etc.).
     * 
     * @return bool True wenn es eine Business-E-Mail ist, false andernfalls
     */
    public function isBusinessEmail(): bool
    {
        $businessDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
        return !in_array($this->getDomain(), $businessDomains, true);
    }

    /**
     * Vergleicht zwei EmailAddress Instanzen auf Gleichheit
     * 
     * @param EmailAddress $other Die andere EmailAddress Instanz
     * @return bool True wenn beide E-Mail-Adressen identisch sind
     */
    public function equals(EmailAddress $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Konvertiert die EmailAddress zu einem String
     * 
     * @return string Die E-Mail-Adresse als String
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Normalisiert eine E-Mail-Adresse für konsistente Verarbeitung
     * 
     * Durchgeführte Normalisierungen:
     * - Entfernung von führenden/trailing Leerzeichen
     * - Konvertierung zu Kleinbuchstaben
     * - Korrektur mehrfacher aufeinanderfolgender Punkte
     * 
     * @param string $email Die zu normalisierende E-Mail-Adresse
     * @return string Die normalisierte E-Mail-Adresse
     */
    private static function normalize(string $email): string
    {
        $email = trim($email);
        $email = strtolower($email);
        
        // Mehrfache Punkte entfernen
        $email = preg_replace('/\.{2,}/', '.', $email);
        
        return $email;
    }

    /**
     * Validiert eine E-Mail-Adresse umfassend
     * 
     * Validierungsschritte:
     * - Prüfung auf Leer-String
     * - Längen-Validierung (RFC 5321 Limit)
     * - PHP Filter-Validierung
     * - Genau ein @-Symbol
     * - Domain-Validierung
     * - Blockierte Domains prüfen
     * 
     * @param string $email Die zu validierende E-Mail-Adresse
     * @throws InvalidEmailAddressException Wenn die Validierung fehlschlägt
     */
    private function validate(string $email): void
    {
        if (empty($email)) {
            throw new InvalidEmailAddressException('Email address cannot be empty');
        }

        if (strlen($email) > self::MAX_LENGTH) {
            throw new InvalidEmailAddressException(
                "Email address too long. Maximum length is " . self::MAX_LENGTH . " characters"
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailAddressException("Invalid email address format: '{$email}'");
        }

        // Zusätzliche Validierung: Genau ein @-Symbol
        if (substr_count($email, '@') !== 1) {
            throw new InvalidEmailAddressException("Email must contain exactly one @ symbol");
        }

        $domain = $this->extractDomain($email);
        
        // Prüfung gegen blockierte Domains
        if (in_array($domain, self::BLOCKED_DOMAINS, true)) {
            throw new InvalidEmailAddressException("Temporary email addresses are not allowed");
        }

        // Domain-Format validieren
        if (!$this->isValidDomain($domain)) {
            throw new InvalidEmailAddressException("Invalid domain format: '{$domain}'");
        }
    }

    /**
     * Extrahiert die Domain aus einer E-Mail-Adresse
     * 
     * @param string $email Die E-Mail-Adresse
     * @return string Die Domain (Teil nach dem @)
     */
    private function extractDomain(string $email): string
    {
        return substr($email, strrpos($email, '@') + 1);
    }

    /**
     * Validiert eine Domain nach RFC-Standards
     * 
     * Validierungsregeln:
     * - Domain darf nicht leer sein
     * - Muss mindestens einen Punkt enthalten
     * - Jeder Domain-Teil darf maximal 63 Zeichen haben
     * - Nur Buchstaben, Zahlen und Bindestriche erlaubt
     * - Darf nicht mit Bindestrich beginnen oder enden
     * 
     * @param string $domain Die zu validierende Domain
     * @return bool True wenn die Domain gültig ist, false andernfalls
     */
    private function isValidDomain(string $domain): bool
    {
        // Basis-Validierung
        if (empty($domain)) {
            return false;
        }

        // Muss mindestens einen Punkt enthalten
        if (!str_contains($domain, '.')) {
            return false;
        }

        // Jeden Teil der Domain prüfen
        $parts = explode('.', $domain);
        foreach ($parts as $part) {
            // Leer oder zu lang
            if (empty($part) || strlen($part) > 63) {
                return false;
            }
            
            // Nur erlaubte Zeichen
            if (!preg_match('/^[a-z0-9-]+$/', $part)) {
                return false;
            }
            
            // Darf nicht mit Bindestrich beginnen oder enden
            if (str_starts_with($part, '-') || str_ends_with($part, '-')) {
                return false;
            }
        }

        return true;
    }
}