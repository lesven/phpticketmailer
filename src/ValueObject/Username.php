<?php

namespace App\ValueObject;

use App\Exception\InvalidUsernameException;

/**
 * Value Object für Benutzernamen
 * 
 * Diese immutable Klasse kapselt die Validierung und Handhabung von Benutzernamen.
 * Sie folgt Domain-Driven Design Prinzipien und stellt sicher, dass nur
 * gültige, sichere Benutzernamen erstellt werden können.
 * 
 * Features:
 * - Längen-Validierung (2-50 Zeichen)
 * - Zeichensatz-Validierung (Alphanumerisch + ._-)
 * - Reservierte Namen-Prüfung (admin, root, etc.)
 * - Normalisierung (Trimming, Lowercase)
 * - Case-insensitive Vergleiche
 * - Sicherheitsvalidierung gegen Injection-Angriffe
 * 
 * @author Generated with Claude Code
 * @since 1.0.0
 */
final readonly class Username
{
    /** @var int Minimale Länge eines Benutzernamens */
    private const MIN_LENGTH = 2;
    
    /** @var int Maximale Länge eines Benutzernamens */
    private const MAX_LENGTH = 50;
    
    /** @var string Erlaubte Zeichen in Benutzernamen (Regex-Pattern) */
    private const VALID_PATTERN = '/^[a-zA-Z0-9._-]+$/';

    /** 
     * Reservierte Benutzernamen die nicht verwendet werden dürfen
     * @var array<string>
     */
    private const RESERVED_NAMES = [
        'admin', 'administrator', 'root', 'system', 'daemon', 'bin', 'sys',
        'sync', 'games', 'man', 'lp', 'mail', 'news', 'uucp', 'proxy',
        'www', 'backup', 'list', 'irc', 'nobody', 'systemd', 'mysql',
        'postgres', 'redis', 'nginx', 'apache', 'ftp', 'ssh', 'git',
        'test', 'testing', 'demo', 'guest', 'anonymous', 'null', 'undefined',
        'api', 'support', 'help', 'info', 'contact', 'noreply', 'postmaster',
        'webmaster', 'hostmaster', 'abuse', 'security', 'privacy'
    ];

    /**
     * Konstruktor für Username
     * 
     * @param string $value Der validierte und normalisierte Benutzername
     */
    public function __construct(private string $value)
    {
        if (empty($value)) {
            throw new InvalidUsernameException('Username cannot be empty');
        }
    }

    /**
     * Erstellt eine Username Instanz aus einem String
     * 
     * Validiert und normalisiert den Benutzernamen.
     * 
     * @param string $username Der rohe Benutzername
     * @return self Eine neue Username Instanz
     * @throws InvalidUsernameException Wenn der Benutzername ungültig ist
     */
    public static function fromString(string $username): self
    {
        $normalized = self::normalize($username);
        self::validate($normalized);
        
        return new self($normalized);
    }

    /**
     * Gibt den String-Wert des Benutzernamens zurück
     * 
     * @return string Der Benutzername als String
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * String-Repräsentation des Benutzernamens
     * 
     * @return string Der Benutzername als String
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Gibt eine Display-Version des Benutzernamens zurück
     * 
     * Nützlich für UI-Anzeigen mit verbesserter Formatierung.
     * 
     * @return string Der Benutzername mit erstem Buchstaben großgeschrieben
     */
    public function getDisplayName(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Vergleicht zwei Username Instanzen auf Gleichheit (case-insensitive)
     * 
     * @param Username $other Die andere Username Instanz
     * @return bool True wenn beide Benutzernamen identisch sind
     */
    public function equals(Username $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    /**
     * Prüft ob der Benutzername reserviert ist
     * 
     * @return bool True wenn der Benutzername in der Reserve-Liste steht
     */
    public function isReserved(): bool
    {
        return in_array(strtolower($this->value), self::RESERVED_NAMES, true);
    }

    /**
     * Prüft ob der Benutzername ein bestimmtes Muster enthält
     * 
     * @param string $pattern Das zu suchende Muster
     * @return bool True wenn das Muster gefunden wird
     */
    public function contains(string $pattern): bool
    {
        return str_contains(strtolower($this->value), strtolower($pattern));
    }

    /**
     * Gibt die Länge des Benutzernamens zurück
     * 
     * @return int Die Anzahl der Zeichen
     */
    public function getLength(): int
    {
        return strlen($this->value);
    }

    /**
     * Prüft ob der Benutzername nur aus Zahlen besteht
     * 
     * @return bool True wenn nur numerische Zeichen vorhanden sind
     */
    public function isNumericOnly(): bool
    {
        return ctype_digit($this->value);
    }

    /**
     * Normalisiert einen Benutzernamen
     * 
     * - Entfernt führende und nachstehende Leerzeichen
     * - Konvertiert zu Kleinbuchstaben für Konsistenz
     * - Entfernt mehrfache Punkte/Unterstriche
     * 
     * @param string $username Der rohe Benutzername
     * @return string Der normalisierte Benutzername
     */
    private static function normalize(string $username): string
    {
        $normalized = trim($username);
        
        // Konvertierung zu Kleinbuchstaben für Konsistenz
        $normalized = strtolower($normalized);
        
        // Entferne aufeinanderfolgende Punkte oder Unterstriche
        $normalized = preg_replace('/[._-]{2,}/', '.', $normalized);
        
        return $normalized;
    }

    /**
     * Validiert einen normalisierten Benutzernamen
     * 
     * Prüft:
     * - Länge (MIN_LENGTH bis MAX_LENGTH)
     * - Erlaubte Zeichen (Alphanumerisch + ._-)
     * - Keine Punkte/Unterstriche am Anfang oder Ende
     * - Nicht in reservierten Namen
     * - Sicherheitsvalidierung gegen Injection-Angriffe
     * 
     * @param string $username Der zu validierende Benutzername
     * @throws InvalidUsernameException Wenn der Benutzername ungültig ist
     */
    private static function validate(string $username): void
    {
        // Leere String Prüfung zuerst
        if (empty($username)) {
            throw new InvalidUsernameException('Username cannot be empty');
        }

        // Längen-Validierung
        $length = strlen($username);
        if ($length < self::MIN_LENGTH) {
            throw new InvalidUsernameException(
                "Username must be at least " . self::MIN_LENGTH . " characters long, got {$length}"
            );
        }

        if ($length > self::MAX_LENGTH) {
            throw new InvalidUsernameException(
                "Username must not exceed " . self::MAX_LENGTH . " characters, got {$length}"
            );
        }

        // Zeichen-Validierung
        if (!preg_match(self::VALID_PATTERN, $username)) {
            throw new InvalidUsernameException(
                'Username contains invalid characters. Only letters, numbers, dots, hyphens and underscores are allowed'
            );
        }

        // Verhindere Punkte/Unterstriche am Anfang oder Ende
        if (preg_match('/^[._-]|[._-]$/', $username)) {
            throw new InvalidUsernameException(
                'Username cannot start or end with dots, hyphens or underscores'
            );
        }

        // Prüfe gegen reservierte Namen
        if (in_array(strtolower($username), self::RESERVED_NAMES, true)) {
            throw new InvalidUsernameException(
                "Username '{$username}' is reserved and cannot be used"
            );
        }

        // Sicherheitsvalidierung: Verhindere potentielle Injection-Angriffe
        self::validateSecurity($username);
    }

    /**
     * Sicherheitsvalidierung gegen Injection-Angriffe
     * 
     * Prüft auf häufige Injection-Patterns und verdächtige Zeichen.
     * 
     * @param string $username Der zu prüfende Benutzername
     * @throws InvalidUsernameException Bei Sicherheitsproblemen
     */
    private static function validateSecurity(string $username): void
    {
        // SQL Injection Patterns
        $sqlPatterns = [
            '/\bselect\b/i', '/\binsert\b/i', '/\bupdate\b/i', '/\bdelete\b/i',
            '/\bdrop\b/i', '/\bunion\b/i', '/\bor\b.*=.*\b/i', '/\band\b.*=.*\b/i',
            '/[\'";]/', '/--/', '/\/\*/', '/\*\//'
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $username)) {
                throw new InvalidUsernameException(
                    'Username contains potentially dangerous characters or patterns'
                );
            }
        }

        // Path Traversal
        if (str_contains($username, '..') || str_contains($username, '/') || str_contains($username, '\\')) {
            throw new InvalidUsernameException(
                'Username cannot contain path traversal characters'
            );
        }

        // XSS Patterns
        if (preg_match('/<[^>]*>/', $username) || str_contains($username, 'javascript:') || str_contains($username, 'data:')) {
            throw new InvalidUsernameException(
                'Username cannot contain HTML tags or javascript'
            );
        }

        // Command Injection
        if (preg_match('/[|&;`$(){}[\]<>]/', $username)) {
            throw new InvalidUsernameException(
                'Username contains characters that could be used for command injection'
            );
        }
    }
}