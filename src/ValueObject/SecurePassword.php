<?php
declare(strict_types=1);

namespace App\ValueObject;

use App\Exception\WeakPasswordException;

/**
 * Value Object für sichere Passwörter
 * 
 * Diese immutable Klasse kapselt Passwort-Hashing, Validierung und Verifikation.
 * Sie folgt Domain-Driven Design Prinzipien und stellt sicher, dass nur
 * starke Passwörter erstellt werden können.
 * 
 * Features:
 * - BCrypt Hashing mit konfigurierbaren Kosten
 * - Passwort-Stärke Validierung
 * - Schwache Passwörter werden abgelehnt
 * - Sichere Passwort-Generierung
 * - Rehashing für Sicherheitsupdates
 * - Unicode-Unterstützung
 * 
 * @author Ihr Name
 * @since 1.0.0
 */
final readonly class SecurePassword
{
    /** @var int Minimale Passwort-Länge */
    private const MIN_LENGTH = 8;
    
    /** @var int Maximale Passwort-Länge */
    private const MAX_LENGTH = 128;
    
    /** @var int BCrypt Kosten-Parameter für Hashing-Performance */
    private const COST = 12;

    /** 
     * Liste häufig verwendeter schwacher Passwörter
     * Diese werden automatisch abgelehnt
     * @var array<string>
     */
    private const WEAK_PASSWORDS = [
        'password', '123456', '12345678', 'qwerty', 'abc123', 'password123',
        'admin', 'letmein', 'welcome', 'monkey', '1234567890', 'geheim'
    ];

    /**
     * Konstruktor für SecurePassword
     * 
     * @param string $hashedValue Der bereits gehashte Passwort-Wert
     * @throws WeakPasswordException Wenn der Hash leer ist
     */
    public function __construct(private string $hashedValue)
    {
        if (empty($hashedValue)) {
            throw new WeakPasswordException('Password hash cannot be empty');
        }
    }

    /**
     * Erstellt eine SecurePassword Instanz aus einem Klartext-Passwort
     * 
     * Das Passwort wird validiert und anschließend mit BCrypt gehasht.
     * 
     * @param string $plaintext Das Klartext-Passwort
     * @return self Eine neue SecurePassword Instanz
     * @throws WeakPasswordException Wenn das Passwort zu schwach ist
     * @throws \RuntimeException Wenn das Hashing fehlschlägt
     */
    public static function fromPlaintext(string $plaintext): self
    {
        self::validatePlaintext($plaintext);
        $hash = password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => self::COST]);
        
        if ($hash === false) {
            throw new \RuntimeException('Failed to hash password');
        }

        return new self($hash);
    }

    /**
     * Erstellt eine SecurePassword Instanz aus einem bereits gehashten Wert
     * 
     * Diese Methode wird hauptsächlich beim Laden aus der Datenbank verwendet.
     * 
     * @param string $hash Der bereits gehashte Passwort-Wert
     * @return self Eine neue SecurePassword Instanz
     */
    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    /**
     * Gibt den gehashten Passwort-Wert zurück
     * 
     * @return string Der BCrypt Hash des Passworts
     */
    public function getHash(): string
    {
        return $this->hashedValue;
    }

    /**
     * Verifiziert ein Klartext-Passwort gegen den gespeicherten Hash
     * 
     * @param string $plaintext Das zu verifizierende Klartext-Passwort
     * @return bool True wenn das Passwort korrekt ist, false andernfalls
     */
    public function verify(string $plaintext): bool
    {
        return password_verify($plaintext, $this->hashedValue);
    }

    /**
     * Prüft ob der Hash neu erstellt werden sollte
     * 
     * Dies ist nützlich wenn sich die Hashing-Parameter geändert haben
     * (z.B. höhere Kosten für bessere Sicherheit).
     * 
     * @return bool True wenn ein Rehash empfohlen wird
     */
    public function needsRehash(): bool
    {
        return password_needs_rehash($this->hashedValue, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }

    /**
     * Erstellt einen neuen Hash mit aktuellen Parametern
     * 
     * @param string $plaintext Das ursprüngliche Klartext-Passwort zur Verifikation
     * @return self Eine neue SecurePassword Instanz mit aktuellem Hash
     * @throws \InvalidArgumentException Wenn das Klartext-Passwort nicht stimmt
     */
    public function rehash(string $plaintext): self
    {
        if (!$this->verify($plaintext)) {
            throw new \InvalidArgumentException('Cannot rehash with wrong plaintext password');
        }

        return self::fromPlaintext($plaintext);
    }

    /**
     * Vergleicht zwei SecurePassword Instanzen sicher auf Gleichheit
     * 
     * Verwendet hash_equals() um Timing-Angriffe zu verhindern.
     * 
     * @param SecurePassword $other Die andere SecurePassword Instanz
     * @return bool True wenn beide Passwörter identisch sind
     */
    public function equals(SecurePassword $other): bool
    {
        return hash_equals($this->hashedValue, $other->hashedValue);
    }

    /**
     * Validiert ein Klartext-Passwort auf Stärke und Sicherheit
     * 
     * Überprüft:
     * - Minimale und maximale Länge
     * - Häufig verwendete schwache Passwörter
     * - Passwort-Komplexität (Großbuchstaben, Kleinbuchstaben, Zahlen, Sonderzeichen)
     * 
     * @param string $plaintext Das zu validierende Passwort
     * @throws WeakPasswordException Wenn das Passwort den Anforderungen nicht entspricht
     */
    private static function validatePlaintext(string $plaintext): void
    {
        // Längen-Validierung
        if (strlen($plaintext) < self::MIN_LENGTH) {
            throw new WeakPasswordException(
                "Password must be at least " . self::MIN_LENGTH . " characters long"
            );
        }

        if (strlen($plaintext) > self::MAX_LENGTH) {
            throw new WeakPasswordException(
                "Password must not exceed " . self::MAX_LENGTH . " characters"
            );
        }

        // Prüfung gegen häufige schwache Passwörter
        $lowercasePassword = strtolower($plaintext);
        if (in_array($lowercasePassword, self::WEAK_PASSWORDS, true)) {
            throw new WeakPasswordException('Password is too common and easily guessable');
        }

        // Passwort-Stärke bewerten
        $score = self::calculateStrengthScore($plaintext);
        if ($score < 3) {
            throw new WeakPasswordException(
                'Password is too weak. Include uppercase, lowercase, numbers, and special characters'
            );
        }
    }

    /**
     * Berechnet einen Stärke-Score für ein Passwort
     * 
     * Bewertungskriterien:
     * - Länge (Bonus für 12+ und 16+ Zeichen)
     * - Zeichenvielfalt (Groß-/Kleinbuchstaben, Zahlen, Sonderzeichen)
     * - Malus für wiederholte Zeichen oder sequenzielle Muster
     * 
     * @param string $password Das zu bewertende Passwort
     * @return int Score von 0 bis ca. 6 (höher = stärker)
     */
    private static function calculateStrengthScore(string $password): int
    {
        $score = 0;

        // Längen-Bonus
        if (strlen($password) >= 12) {
            $score++; // +1 für gute Länge
        }

        if (strlen($password) >= 16) {
            $score++; // +1 für sehr gute Länge
        }

        // Zeichenvielfalt (mit Unicode-Unterstützung)
        if (preg_match('/[a-z\p{Ll}]/u', $password)) {
            $score++; // Kleinbuchstaben (inkl. Unicode)
        }

        if (preg_match('/[A-Z\p{Lu}]/u', $password)) {
            $score++; // Großbuchstaben (inkl. Unicode)
        }

        if (preg_match('/[0-9]/', $password)) {
            $score++; // Zahlen
        }

        if (preg_match('/[^A-Za-z0-9\p{L}]/u', $password)) {
            $score++; // Sonderzeichen
        }

        // Malus für schlechte Patterns
        if (preg_match('/(.)\1{2,}/', $password)) {
            $score--; // 3+ wiederholte Zeichen
        }

        if (preg_match('/123|abc|qwe|asd/i', $password)) {
            $score--; // Sequenzielle Muster
        }

        return max(0, $score); // Mindestens 0
    }

    /**
     * Generiert ein sicheres zufälliges Passwort
     * 
     * Das generierte Passwort enthält garantiert:
     * - Mindestens einen Großbuchstaben
     * - Mindestens einen Kleinbuchstaben  
     * - Mindestens eine Zahl
     * - Mindestens ein Sonderzeichen
     * 
     * @param int $length Gewünschte Passwort-Länge (Standard: 16)
     * @return self Eine neue SecurePassword Instanz mit generiertem Passwort
     * @throws \InvalidArgumentException Wenn die Länge außerhalb der Grenzen liegt
     */
    public static function generateSecure(int $length = 16): self
    {
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Invalid password length');
        }

        // Zeichensätze definieren
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        // Mindestens ein Zeichen aus jeder Kategorie
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Restliche Länge mit zufälligen Zeichen füllen
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Passwort mischen um vorhersagbare Reihenfolge zu vermeiden
        $password = str_shuffle($password);

        return self::fromPlaintext($password);
    }
}