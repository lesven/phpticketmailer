<?php

namespace App\Exception;

/**
 * Exception für Fehler beim E-Mail-Versand
 * 
 * Wird geworfen, wenn beim Versenden von E-Mails Probleme auftreten,
 * sei es durch SMTP-Konfiguration, Netzwerkprobleme oder andere Ursachen.
 */
class EmailSendingException extends TicketMailerException
{
    /**
     * Erstellt eine Exception für SMTP-Konfigurationsfehler
     */
    public static function smtpConfigurationError(string $details, array $context = []): self
    {
        return new self(
            message: "SMTP-Konfigurationsfehler: {$details}",
            context: array_merge($context, ['type' => 'smtp_configuration'])
        );
    }

    /**
     * Erstellt eine Exception für Verbindungsfehler zum SMTP-Server
     */
    public static function connectionError(string $host, int $port, ?\Throwable $previous = null): self
    {
        return new self(
            message: "Verbindung zum SMTP-Server {$host}:{$port} fehlgeschlagen",
            previous: $previous,
            context: [
                'type' => 'connection_error',
                'smtp_host' => $host,
                'smtp_port' => $port
            ]
        );
    }

    /**
     * Erstellt eine Exception für Authentifizierungsfehler
     */
    public static function authenticationError(string $username, ?\Throwable $previous = null): self
    {
        return new self(
            message: "SMTP-Authentifizierung für Benutzer '{$username}' fehlgeschlagen",
            previous: $previous,
            context: [
                'type' => 'authentication_error',
                'smtp_username' => $username
            ]
        );
    }

    /**
     * Erstellt eine Exception für ungültige E-Mail-Adressen
     */
    public static function invalidEmailAddress(string $email, array $context = []): self
    {
        return new self(
            message: "Ungültige E-Mail-Adresse: {$email}",
            context: array_merge($context, [
                'type' => 'invalid_email',
                'email' => $email
            ])
        );
    }

    /**
     * Erstellt eine Exception für fehlende Template-Dateien
     */
    public static function templateNotFound(string $templatePath): self
    {
        return new self(
            message: "E-Mail-Template nicht gefunden: {$templatePath}",
            context: [
                'type' => 'template_not_found',
                'template_path' => $templatePath
            ]
        );
    }

    public function getUserMessage(): string
    {
        $context = $this->getContext();
        
        return match ($context['type'] ?? 'unknown') {
            'smtp_configuration' => 'Die E-Mail-Konfiguration ist fehlerhaft. Bitte überprüfen Sie die SMTP-Einstellungen.',
            'connection_error' => 'Der E-Mail-Server ist nicht erreichbar. Bitte versuchen Sie es später erneut.',
            'authentication_error' => 'Die Anmeldung am E-Mail-Server ist fehlgeschlagen. Bitte überprüfen Sie Benutzername und Passwort.',
            'invalid_email' => 'Eine oder mehrere E-Mail-Adressen sind ungültig.',
            'template_not_found' => 'Das E-Mail-Template wurde nicht gefunden.',
            default => 'Beim Versenden der E-Mails ist ein Fehler aufgetreten.'
        };
    }
}
