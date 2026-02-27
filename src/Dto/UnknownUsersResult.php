<?php
declare(strict_types=1);

namespace App\Dto;

/**
 * Value Object für das Ergebnis der Verarbeitung unbekannter Benutzer
 */
readonly class UnknownUsersResult
{
    private function __construct(
        public bool $success,
        public int $newUsersCount,
        public string $message,
        public string $flashType
    ) {
    }

    /**
     * Erstellt ein erfolgreiches Ergebnis
     */
    public static function success(int $newUsersCount): self
    {
        $message = $newUsersCount === 1 
            ? '1 neuer Benutzer wurde erfolgreich angelegt'
            : "{$newUsersCount} neue Benutzer wurden erfolgreich angelegt";
            
        return new self(
            success: true,
            newUsersCount: $newUsersCount,
            message: $message,
            flashType: 'success'
        );
    }

    /**
     * Erstellt ein Ergebnis für den Fall, dass keine Benutzer gefunden wurden
     */
    public static function noUsersFound(): self
    {
        return new self(
            success: false,
            newUsersCount: 0,
            message: 'Keine unbekannten Benutzer zu verarbeiten',
            flashType: 'warning'
        );
    }
}
