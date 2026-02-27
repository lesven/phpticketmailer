<?php
declare(strict_types=1);

namespace App\Service;

use App\Dto\CsvProcessingResult;
use App\ValueObject\UnknownUserWithTicket;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service zur Verwaltung von Session-Daten für den CSV-Upload-Prozess
 * 
 * Zentralisiert den Zugriff auf Session-Daten und sorgt für einheitliche
 * Schlüsselnamen und Datenformate.
 */
final class SessionManager
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
     */
    public function storeUploadResults(CsvProcessingResult $processingResult): void
    {
        $session = $this->requestStack->getSession();
        
        // Clear old session data to ensure clean state
        $session->remove(self::UNKNOWN_USERS_KEY);
        $session->remove(self::VALID_TICKETS_KEY);
        
        // Convert UnknownUserWithTicket objects to arrays for session storage
        $serializedUnknownUsers = [];
        
        foreach ($processingResult->unknownUsers as $unknownUser) {
            $serializedUnknownUsers[] = [
                'type' => 'UnknownUserWithTicket',
                'username' => $unknownUser->getUsernameString(),
                'ticketId' => $unknownUser->getTicketIdString(),
                'ticketName' => $unknownUser->getTicketNameString(),
                'created' => $unknownUser->getCreatedString()
            ];
        }
        
        $session->set(self::UNKNOWN_USERS_KEY, $serializedUnknownUsers);
        $session->set(self::VALID_TICKETS_KEY, $processingResult->validTickets);
    }

    /**
     * Holt die Liste unbekannter Benutzer aus der Session
     * 
     * @return UnknownUserWithTicket[]
     */
    public function getUnknownUsers(): array
    {
        $serializedData = $this->requestStack->getSession()->get(self::UNKNOWN_USERS_KEY, []);
        $unknownUsers = [];
        
        foreach ($serializedData as $item) {
            if (!is_array($item) || !isset($item['type'])) {
                continue;
            }
            
            try {
                if (empty($item['username'])) {
                    continue;
                }
                
                $unknownUsers[] = new \App\ValueObject\UnknownUserWithTicket(
                    new \App\ValueObject\Username($item['username']),
                    new \App\ValueObject\TicketId($item['ticketId'] ?? 'UNKNOWN'),
                    isset($item['ticketName']) && $item['ticketName'] ? new \App\ValueObject\TicketName($item['ticketName']) : null,
                    $item['created'] ?? null
                );
            } catch (\Exception $e) {
                // Skip invalid entries without breaking the whole process
                continue;
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
