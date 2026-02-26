<?php

namespace App\Dto;

/**
 * Value Object für das Ergebnis eines Benutzer-Import-Vorgangs
 */
readonly class UserImportResult
{
    private function __construct(
        public bool $success,
        public int $createdCount,
        public int $skippedCount,
        public array $errors,
        public string $message
    ) {
    }

    /**
     * Erstellt ein erfolgreiches Import-Ergebnis
     */
    public static function success(int $createdCount, int $skippedCount = 0, array $errors = []): self
    {
        $message = sprintf(
            '%d Benutzer erfolgreich importiert',
            $createdCount
        );

        if ($skippedCount > 0) {
            $message .= sprintf(', %d übersprungen', $skippedCount);
        }

        if (!empty($errors)) {
            $message .= sprintf(', %d Fehler', count($errors));
        }

        return new self(
            success: true,
            createdCount: $createdCount,
            skippedCount: $skippedCount,
            errors: $errors,
            message: $message
        );
    }

    /**
     * Erstellt ein Fehler-Ergebnis
     */
    public static function error(string $message): self
    {
        return new self(
            success: false,
            createdCount: 0,
            skippedCount: 0,
            errors: [],
            message: $message
        );
    }

    /**
     * Gibt zurück, ob der Import Fehler hatte
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Gibt die Flash-Message-Type basierend auf dem Ergebnis zurück
     */
    public function getFlashType(): string
    {
        if (!$this->success) {
            return 'error';
        }

        if ($this->hasErrors()) {
            return 'warning';
        }

        return 'success';
    }
}
