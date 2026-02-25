<?php

namespace App\Service;

use App\Entity\EmailSent;

/**
 * Interface für den E-Mail-Versand-Service.
 *
 * Erlaubt einfaches Mocking in Tests und definiert die öffentliche API
 * für den E-Mail-Versandprozess.
 */
interface EmailServiceInterface
{
    /**
     * Sendet E-Mails für alle übergebenen Ticket-Datensätze mit Duplikatsprüfung.
     *
     * @param \App\ValueObject\TicketData[] $ticketData Liste der Ticket-Daten
     * @param bool $testMode Gibt an, ob die E-Mails im Testmodus gesendet werden sollen
     * @param bool $forceResend Gibt an, ob bereits verarbeitete Tickets erneut versendet werden sollen
     * @param string|null $customTestEmail Optionale Test-E-Mail-Adresse für den Testmodus
     * @return EmailSent[] Array mit allen erstellten EmailSent-Entitäten
     */
    public function sendTicketEmailsWithDuplicateCheck(
        array $ticketData,
        bool $testMode = false,
        bool $forceResend = false,
        ?string $customTestEmail = null,
    ): array;

    /**
     * Gibt die Template-Debug-Infos pro Ticket zurück.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTemplateDebugInfo(): array;
}
