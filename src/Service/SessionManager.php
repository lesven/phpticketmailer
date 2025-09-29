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
    private const TEST_EMAIL_KEY = 'test_email';

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
        
        // Clear old session data to ensure clean state
        $session->remove(self::UNKNOWN_USERS_KEY);
        $session->remove(self::VALID_TICKETS_KEY);
        
        // Convert UnknownUserWithTicket objects to arrays for session storage
        $unknownUsers = $processingResult['unknownUsers'] ?? [];
        $serializedUnknownUsers = [];
        
        foreach ($unknownUsers as $unknownUser) {
            if ($unknownUser instanceof \App\ValueObject\UnknownUserWithTicket) {
                $serializedUnknownUsers[] = [
                    'type' => 'UnknownUserWithTicket',
                    'username' => $unknownUser->getUsernameString(),
                    'ticketId' => $unknownUser->getTicketIdString(),
                    'ticketName' => $unknownUser->getTicketNameString()
                ];
            } else {
                // Fallback for strings (backward compatibility)
                $serializedUnknownUsers[] = [
                    'type' => 'string',
                    'username' => $unknownUser
                ];
            }
        }
        
        $session->set(self::UNKNOWN_USERS_KEY, $serializedUnknownUsers);
        $session->set(self::VALID_TICKETS_KEY, $processingResult['validTickets'] ?? []);
    }

    /**
     * Holt die Liste unbekannter Benutzer aus der Session
     * 
     * @return array Liste der unbekannten Benutzernamen (UnknownUserWithTicket objects oder Strings)
     */
    public function getUnknownUsers(): array
    {
        $serializedData = $this->requestStack->getSession()->get(self::UNKNOWN_USERS_KEY, []);
        $unknownUsers = [];
        
        foreach ($serializedData as $item) {
            if (is_array($item) && isset($item['type'])) {
                if ($item['type'] === 'UnknownUserWithTicket') {
                    // Reconstruct UnknownUserWithTicket object with validation
                    try {
                        if (empty($item['username']) || empty($item['ticketId'])) {
                            continue; // Skip invalid entries
                        }
                        
                        $unknownUsers[] = new \App\ValueObject\UnknownUserWithTicket(
                            new \App\ValueObject\Username($item['username']),
                            new \App\ValueObject\TicketId($item['ticketId']),
                            $item['ticketName'] ? new \App\ValueObject\TicketName($item['ticketName']) : null
                        );
                    } catch (\Exception $e) {
                        // Skip invalid entries without breaking the whole process
                        continue;
                    }
                } else {
                    // Fallback for string type
                    if (!empty($item['username'])) {
                        $unknownUsers[] = $item['username'];
                    }
                }
            } else {
                // Legacy format - direct strings
                $unknownUsers[] = $item;
            }
        }
        
        return $unknownUsers;
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
     * Speichert die Test-E-Mail-Adresse in der Session
     * 
     * @param string|null $testEmail Die Test-E-Mail-Adresse
     */
    public function storeTestEmail(?string $testEmail): void
    {
        $session = $this->requestStack->getSession();
        
        if ($testEmail !== null && !empty(trim($testEmail))) {
            $session->set(self::TEST_EMAIL_KEY, trim($testEmail));
        } else {
            $session->remove(self::TEST_EMAIL_KEY);
        }
    }

    /**
     * Holt die Test-E-Mail-Adresse aus der Session
     * 
     * @return string|null Die gespeicherte Test-E-Mail-Adresse oder null
     */
    public function getTestEmail(): ?string
    {
        return $this->requestStack->getSession()->get(self::TEST_EMAIL_KEY);
    }

    /**
     * Löscht die Upload-Daten aus der Session
     */
    public function clearUploadData(): void
    {
        $session = $this->requestStack->getSession();
        
        $session->remove(self::UNKNOWN_USERS_KEY);
        $session->remove(self::VALID_TICKETS_KEY);
        $session->remove(self::TEST_EMAIL_KEY);
    }
}
