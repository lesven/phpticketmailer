<?php

namespace App\Exception;

/**
 * Exception für Validierungsfehler bei Benutzereingaben
 * 
 * Wird geworfen, wenn Formulardaten oder andere Benutzereingaben
 * die Validierung nicht bestehen (z.B. ungültige E-Mail-Adressen).
 */
class ValidationException extends TicketMailerException
{
    /**
     * Erstellt eine Exception für eine ungültige E-Mail-Adresse eines Benutzers
     */
    public static function invalidEmailForUser(string $username, string $details): self
    {
        return new self(
            message: "Ungültige E-Mail-Adresse für Benutzer '{$username}': {$details}",
            context: [
                'type' => 'validation_error',
                'field' => 'email',
                'username' => $username,
            ]
        );
    }

    /**
     * Erstellt eine generische Validierungs-Exception
     */
    public static function invalidField(string $fieldName, string $details): self
    {
        return new self(
            message: "Ungültiger Wert für '{$fieldName}': {$details}",
            context: [
                'type' => 'validation_error',
                'field' => $fieldName,
            ]
        );
    }
}
