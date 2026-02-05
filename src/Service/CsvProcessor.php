<?php
/**
 * CsvProcessor.php
 * 
 * Diese Klasse ist verantwortlich für die Verarbeitung von CSV-Dateien
 * mit Ticket-Daten. Sie nutzt spezialisierte Services für die
 * CSV-Dateiverarbeitung und Benutzervalidierung.
 * 
 * @package App\Service
 */

namespace App\Service;

use App\Entity\CsvFieldConfig;
use App\Repository\UserRepository;
use App\ValueObject\TicketData;
use App\ValueObject\UnknownUserWithTicket;
use App\ValueObject\Username;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

class CsvProcessor
{
    /**
     * @var CsvFileReader
     */
    private CsvFileReader $csvFileReader;
    
    /**
     * @var UserRepository
     */
    private UserRepository $userRepository;
    
    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;
    
    /**
     * Konstruktor mit Dependency Injection aller benötigten Services
     */
    public function __construct(
        CsvFileReader $csvFileReader,
        UserRepository $userRepository,
        RequestStack $requestStack
    ) {
        $this->csvFileReader = $csvFileReader;
        $this->userRepository = $userRepository;
        $this->requestStack = $requestStack;
    }
      /**
     * Verarbeitet eine hochgeladene CSV-Datei mit Ticket-Daten
     * 
     * Diese Methode koordiniert die CSV-Verarbeitung und gibt strukturierte Ergebnisse zurück.
     * 
     * @param UploadedFile $file Die hochgeladene CSV-Datei
     * @param CsvFieldConfig $csvFieldConfig Konfiguration der CSV-Feldnamen
     * @return array{validTickets: array, invalidRows: array, unknownUsers: array} Ergebnisse der Verarbeitung
     * @throws \Exception Bei Fehlern während der Verarbeitung
     */
    public function process(UploadedFile $file, CsvFieldConfig $csvFieldConfig): array
    {
        $result = [
            'validTickets' => [],
            'invalidRows' => [],
            'unknownUsers' => []
        ];

        // Konfigurierte Feldnamen holen
        $fieldMapping = $csvFieldConfig->getFieldMapping();
        $requiredColumns = [
            $fieldMapping['ticketId'],
            $fieldMapping['username'],
            $fieldMapping['ticketName'],
        ];
        
        $handle = null;
        try {
            // CSV-Datei öffnen und Header lesen
            $handle = $this->csvFileReader->openCsvFile($file);
            $header = $this->csvFileReader->readHeader($handle);
            $columnIndices = $this->csvFileReader->validateRequiredColumns($header, $requiredColumns);
            
            // Daten zur Ticketverarbeitung
            $validTickets = [];
            $invalidRows = [];
            $uniqueUsernames = [];
            
            // Zeilen verarbeiten
            $this->csvFileReader->processRows($handle, function ($row, $rowNumber) use (
                &$validTickets,
                &$invalidRows,
                &$uniqueUsernames,
                $columnIndices,
                $fieldMapping
            ) {
                if (!$this->isRowValid($row, $columnIndices, $fieldMapping)) {
                    $invalidRows[] = [
                        'rowNumber' => $rowNumber,
                        'data' => $row
                    ];
                    return;
                }
                
                try {
                    $ticket = $this->createTicketFromRow($row, $columnIndices, $fieldMapping);
                    $validTickets[] = $ticket;
                    
                    // Username nur bei erfolgreich erstelltem Ticket hinzufügen
                    $username = $row[$columnIndices[$fieldMapping['username']]];
                    $uniqueUsernames[$username] = true;
                } catch (\App\Exception\InvalidUsernameException | \App\Exception\InvalidTicketIdException | \App\Exception\InvalidTicketNameException $e) {
                    // Ungültige Zeilen werden zu invalidRows hinzugefügt anstatt die Verarbeitung zu stoppen
                    $invalidRows[] = [
                        'rowNumber' => $rowNumber,
                        'data' => $row,
                        'error' => $e->getMessage()
                    ];
                }
            });
            
            $result['validTickets'] = $validTickets;
            $result['invalidRows'] = $invalidRows;
            
            // Get basic unknown users using the existing method (for backward compatibility)
            $basicUnknownUsers = $this->userRepository->identifyUnknownUsers($uniqueUsernames);
            
            // Enhance with ticket information for display purposes
            $result['unknownUsers'] = $this->enhanceUnknownUsersWithTicketInfo($basicUnknownUsers, $validTickets);
            
            // Speichere die gültigen Tickets in der Session für späteren Zugriff
            $this->storeTicketsInSession($validTickets);
            
        } finally {
            // Datei-Handle sicher schließen
            $this->csvFileReader->closeHandle($handle);
        }
        
        return $result;
    }

    /**
     * Erweitert eine Liste von unbekannten Benutzern mit Ticket-Kontext
     * 
     * @param array $unknownUsernames Liste der unbekannten Benutzernamen (Strings)
     * @param array $validTickets Liste der gültigen Ticket-Daten
     * @return array Array von UnknownUserWithTicket Objekten oder Strings (für Backward Compatibility)
     */
    private function enhanceUnknownUsersWithTicketInfo(array $unknownUsernames, array $validTickets): array
    {
        if (empty($unknownUsernames)) {
            return [];
        }

        // Erstelle Mapping von Username zu Ticket für schnellen Zugriff
        $ticketsByUsername = [];
        foreach ($validTickets as $ticket) {
            $usernameString = $ticket->username->getValue();
            // Use lowercase for case-insensitive matching
            $lowercaseUsername = strtolower($usernameString);
            if (!isset($ticketsByUsername[$lowercaseUsername])) {
                $ticketsByUsername[$lowercaseUsername] = $ticket;
            }
        }

        // Erstelle enhanced unknown users mit Ticket-Information
        $enhancedUnknownUsers = [];
        foreach ($unknownUsernames as $usernameString) {
            $lowercaseUnknownUser = strtolower($usernameString);
            if (isset($ticketsByUsername[$lowercaseUnknownUser])) {
                $enhancedUnknownUsers[] = UnknownUserWithTicket::fromTicketData($ticketsByUsername[$lowercaseUnknownUser]);
            } else {
                // Fallback für den Fall, dass kein Ticket gefunden wird (sollte nicht passieren)
                $enhancedUnknownUsers[] = $usernameString;
            }
        }
        
        return $enhancedUnknownUsers;
    }

    /**
     * Prüft, ob eine Zeile alle erforderlichen Werte enthält
     * 
     * @param array $row Die zu prüfende Zeile
     * @param array $columnIndices Die Indizes der benötigten Spalten
     * @param array $fieldMapping Die Zuordnung der logischen zu physischen Feldnamen
     * @return bool True, wenn die Zeile gültig ist, sonst False
     */
    private function isRowValid(array $row, array $columnIndices, array $fieldMapping): bool
    {
        return isset($row[$columnIndices[$fieldMapping['ticketId']]]) && !empty($row[$columnIndices[$fieldMapping['ticketId']]]) &&
               isset($row[$columnIndices[$fieldMapping['username']]]) && !empty($row[$columnIndices[$fieldMapping['username']]]);
    }
    
    /**
     * Erstellt ein TicketData-Objekt aus einer CSV-Zeile
     *
     * @param array $row Die CSV-Zeile
     * @param array $columnIndices Die Indizes der benötigten Spalten
     * @param array $fieldMapping Die Zuordnung der logischen zu physischen Feldnamen
     */
    private function createTicketFromRow(array $row, array $columnIndices, array $fieldMapping): TicketData
    {
        $ticketNameRaw = $row[$columnIndices[$fieldMapping['ticketName']]] ?? null;
        $createdRaw = isset($fieldMapping['created'], $columnIndices[$fieldMapping['created']])
            ? ($row[$columnIndices[$fieldMapping['created']]] ?? null)
            : null;

        return TicketData::fromStrings(
            $row[$columnIndices[$fieldMapping['ticketId']]],
            $row[$columnIndices[$fieldMapping['username']]],
            $ticketNameRaw,
            $createdRaw
        );
    }
    
    /**
     * Speichert die gültigen Tickets in der Session
     * 
     * @param array $validTickets Die zu speichernden gültigen Tickets
     */
    private function storeTicketsInSession(array $validTickets): void
    {
        $this->requestStack->getSession()->set('valid_tickets', $validTickets);
    }
}