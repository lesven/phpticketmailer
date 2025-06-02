<?php

namespace App\Service;

/**
 * Value Object für das Ergebnis eines CSV-Upload-Vorgangs
 * 
 * Kapselt alle Informationen über das Verarbeitungsergebnis und
 * die erforderlichen Weiterleitungen.
 */
readonly class UploadResult
{
    private function __construct(
        public string $redirectRoute,
        public array $routeParameters,
        public string $flashMessage,
        public string $flashType
    ) {
    }

    /**
     * Erstellt ein Ergebnis für die Weiterleitung zu unbekannten Benutzern
     */
    public static function redirectToUnknownUsers(
        bool $testMode, 
        bool $forceResend, 
        int $unknownUsersCount
    ): self {
        return new self(
            redirectRoute: 'unknown_users',
            routeParameters: [
                'testMode' => $testMode ? 1 : 0,
                'forceResend' => $forceResend ? 1 : 0
            ],
            flashMessage: sprintf('Es wurden %d unbekannte Benutzer gefunden', $unknownUsersCount),
            flashType: 'info'
        );
    }

    /**
     * Erstellt ein Ergebnis für die direkte Weiterleitung zum E-Mail-Versand
     */
    public static function redirectToEmailSending(bool $testMode, bool $forceResend): self
    {
        return new self(
            redirectRoute: 'send_emails',
            routeParameters: [
                'testMode' => $testMode ? 1 : 0,
                'forceResend' => $forceResend ? 1 : 0
            ],
            flashMessage: 'CSV-Datei erfolgreich verarbeitet',
            flashType: 'success'
        );
    }

    /**
     * Erstellt ein Fehler-Ergebnis
     */
    public static function error(string $message): self
    {
        return new self(
            redirectRoute: 'csv_upload',
            routeParameters: [],
            flashMessage: $message,
            flashType: 'error'
        );
    }
}
