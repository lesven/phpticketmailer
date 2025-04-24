<?php
/**
 * CsvProcessor.php
 * 
 * Diese Klasse ist verantwortlich für die Verarbeitung von CSV-Dateien
 * mit Ticket-Daten. Sie validiert die Daten, identifiziert unbekannte Benutzer
 * und bereitet die Daten für den E-Mail-Versand vor.
 * 
 * @package App\Service
 */

namespace App\Service;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

class CsvProcessor
{
    /**
     * @var array<string, string> Erforderliche CSV-Spalten
     */
    private const REQUIRED_COLUMNS = [
        'ticketId' => 'Ticket-ID',
        'username' => 'Benutzername',
        'ticketName' => 'Ticket-Name'
    ];
    
    /**
     * @var string Das Trennzeichen für die CSV-Datei
     */
    private const CSV_DELIMITER = ',';
    
    /**
     * @var int Die maximale Länge einer CSV-Zeile
     */
    private const MAX_LINE_LENGTH = 1000;

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
        UserRepository $userRepository,
        RequestStack $requestStack
    ) {
        $this->userRepository = $userRepository;
        $this->requestStack = $requestStack;
    }
    
    /**
     * Verarbeitet eine hochgeladene CSV-Datei mit Ticket-Daten
     * 
     * Diese Methode koordiniert die CSV-Verarbeitung und gibt strukturierte Ergebnisse zurück.
     * 
     * @param UploadedFile $file Die hochgeladene CSV-Datei
     * @return array{validTickets: array, invalidRows: array, unknownUsers: array} Ergebnisse der Verarbeitung
     * @throws \Exception Bei Fehlern während der Verarbeitung
     */
    public function process(UploadedFile $file): array
    {
        $result = [
            'validTickets' => [],
            'invalidRows' => [],
            'unknownUsers' => []
        ];
        
        $handle = null;
        try {
            $handle = fopen($file->getPathname(), 'r');
            if ($handle === false) {
                throw new \Exception('CSV-Datei konnte nicht geöffnet werden');
            }

            $header = $this->readAndValidateHeader($handle);
            $columnIndices = $this->getColumnIndices($header);
            
            [$validTickets, $invalidRows, $uniqueUsernames] = $this->processRows(
                $handle, 
                $columnIndices['ticketId'],
                $columnIndices['username'],
                $columnIndices['ticketName']
            );
            
            $result['validTickets'] = $validTickets;
            $result['invalidRows'] = $invalidRows;
            $result['unknownUsers'] = $this->identifyUnknownUsers($uniqueUsernames);
            
            // Speichere die gültigen Tickets in der Session für späteren Zugriff
            $this->storeTicketsInSession($validTickets);
            
        } finally {
            if ($handle !== null && $handle !== false) {
                fclose($handle);
            }
        }
        
        return $result;
    }
    
    /**
     * Liest und validiert den CSV-Header
     * 
     * @param resource $handle Der Datei-Handle
     * @return array Der CSV-Header als Array
     * @throws \Exception Wenn der Header nicht gelesen werden kann
     */
    private function readAndValidateHeader($handle): array
    {
        $header = fgetcsv($handle, self::MAX_LINE_LENGTH, self::CSV_DELIMITER);
        if ($header === false) {
            throw new \Exception('CSV-Header konnte nicht gelesen werden');
        }
        return $header;
    }
    
    /**
     * Ermittelt die Indizes der benötigten Spalten im Header
     * 
     * @param array $header Der CSV-Header
     * @return array<string, int> Die Indizes der benötigten Spalten
     * @throws \Exception Wenn nicht alle erforderlichen Spalten vorhanden sind
     */
    private function getColumnIndices(array $header): array
    {
        $indices = [];
        $missingColumns = [];
        
        foreach (array_keys(self::REQUIRED_COLUMNS) as $columnName) {
            $index = array_search($columnName, $header);
            if ($index === false) {
                $missingColumns[] = $columnName;
            } else {
                $indices[$columnName] = $index;
            }
        }
        
        if (!empty($missingColumns)) {
            throw new \Exception(sprintf(
                'CSV-Datei enthält nicht alle erforderlichen Spalten: %s',
                implode(', ', $missingColumns)
            ));
        }
        
        return $indices;
    }
    
    /**
     * Verarbeitet die CSV-Zeilen und extrahiert Ticket-Daten
     * 
     * @param resource $handle Der Datei-Handle
     * @param int $ticketIdIndex Der Index der ticketId-Spalte
     * @param int $usernameIndex Der Index der username-Spalte
     * @param int $ticketNameIndex Der Index der ticketName-Spalte
     * @return array Ein Array mit [validTickets, invalidRows, uniqueUsernames]
     */
    private function processRows($handle, int $ticketIdIndex, int $usernameIndex, int $ticketNameIndex): array
    {
        $rowNumber = 1; // Header ist Zeile 1
        $validTickets = [];
        $invalidRows = [];
        $uniqueUsernames = [];
        
        while (($row = fgetcsv($handle, self::MAX_LINE_LENGTH, self::CSV_DELIMITER)) !== false) {
            $rowNumber++;
            
            if (!$this->isRowValid($row, $ticketIdIndex, $usernameIndex, $ticketNameIndex)) {
                $invalidRows[] = [
                    'rowNumber' => $rowNumber,
                    'data' => $row
                ];
                continue;
            }
            
            $username = $row[$usernameIndex];
            $uniqueUsernames[$username] = true;
            
            $validTickets[] = $this->createTicketFromRow($row, $ticketIdIndex, $usernameIndex, $ticketNameIndex);
        }
        
        return [$validTickets, $invalidRows, $uniqueUsernames];
    }
    
    /**
     * Prüft, ob eine Zeile alle erforderlichen Werte enthält
     * 
     * @param array $row Die zu prüfende Zeile
     * @param int $ticketIdIndex Der Index der ticketId-Spalte
     * @param int $usernameIndex Der Index der username-Spalte
     * @param int $ticketNameIndex Der Index der ticketName-Spalte
     * @return bool True, wenn die Zeile gültig ist, sonst False
     */
    private function isRowValid(array $row, int $ticketIdIndex, int $usernameIndex, int $ticketNameIndex): bool
    {
        return isset($row[$ticketIdIndex]) && !empty($row[$ticketIdIndex]) &&
               isset($row[$usernameIndex]) && !empty($row[$usernameIndex]) &&
               isset($row[$ticketNameIndex]);
    }
    
    /**
     * Erstellt ein Ticket-Array aus einer CSV-Zeile
     * 
     * @param array $row Die CSV-Zeile
     * @param int $ticketIdIndex Der Index der ticketId-Spalte
     * @param int $usernameIndex Der Index der username-Spalte
     * @param int $ticketNameIndex Der Index der ticketName-Spalte
     * @return array Das Ticket als assoziatives Array
     */
    private function createTicketFromRow(array $row, int $ticketIdIndex, int $usernameIndex, int $ticketNameIndex): array
    {
        return [
            'ticketId' => $row[$ticketIdIndex],
            'username' => $row[$usernameIndex],
            'ticketName' => $row[$ticketNameIndex] ?: null,
        ];
    }
    
    /**
     * Identifiziert unbekannte Benutzer (ohne E-Mail-Adresse)
     * 
     * @param array $uniqueUsernames Array mit eindeutigen Benutzernamen
     * @return array Liste der unbekannten Benutzernamen
     */
    private function identifyUnknownUsers(array $uniqueUsernames): array
    {
        if (empty($uniqueUsernames)) {
            return [];
        }
        
        $unknownUsers = [];
        $users = $this->userRepository->findMultipleByUsernames(array_keys($uniqueUsernames));
        $knownUsernames = [];
        
        foreach ($users as $user) {
            $knownUsernames[$user->getUsername()] = true;
        }
        
        foreach (array_keys($uniqueUsernames) as $username) {
            if (!isset($knownUsernames[$username])) {
                $unknownUsers[] = $username;
            }
        }
        
        return $unknownUsers;
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