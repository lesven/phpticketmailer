<?php

namespace App\Service;

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
        return new self(
            success: true,
            newUsersCount: $newUsersCount,
            message: 'Neue Benutzer wurden erfolgreich angelegt',
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
