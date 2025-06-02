<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service zur Verwaltung von Session-Daten für den CSV-Upload-Prozess
 * 
 * Zentralisiert den Zugriff auf Session-Daten und sorgt für einheitliche
 * Schlüsselnamen und Datenformate.
 */
class SessionManager
{
    private const UNKNOWN_USERS_KEY = 'unknown_users';
    private const VALID_TICKETS_KEY = 'valid_tickets';

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Speichert die Ergebnisse eines CSV-Upload-Vorgangs in der Session
     * 
     * @param array $processingResult Ergebnis der CSV-Verarbeitung
     */
    public function storeUploadResults(array $processingResult): void
    {
        $session = $this->requestStack->getSession();
        
        $session->set(self::UNKNOWN_USERS_KEY, $processingResult['unknownUsers'] ?? []);
        $session->set(self::VALID_TICKETS_KEY, $processingResult['validTickets'] ?? []);
    }

    /**
     * Holt die Liste unbekannter Benutzer aus der Session
     * 
     * @return array Liste der unbekannten Benutzernamen
     */
    public function getUnknownUsers(): array
    {
        return $this->requestStack->getSession()->get(self::UNKNOWN_USERS_KEY, []);
    }

    /**
     * Holt die Liste gültiger Tickets aus der Session
     * 
     * @return array Liste der gültigen Ticket-Daten
     */
    public function getValidTickets(): array
    {
        return $this->requestStack->getSession()->get(self::VALID_TICKETS_KEY, []);
    }

    /**
     * Löscht die Upload-Daten aus der Session
     */
    public function clearUploadData(): void
    {
        $session = $this->requestStack->getSession();
        
        $session->remove(self::UNKNOWN_USERS_KEY);
        $session->remove(self::VALID_TICKETS_KEY);
    }
}
