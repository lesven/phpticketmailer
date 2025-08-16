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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

class CsvProcessor
{
    /**
     * @var array<string> Erforderliche CSV-Spalten
     */
    private const REQUIRED_COLUMNS = [
        'ticketId',
        'username',
        'ticketName'
    ];
    
    /**
     * @var CsvFileReader
     */
    private CsvFileReader $csvFileReader;
    
    /**
     * @var UserValidator
     */
    private UserValidator $userValidator;
    
    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;
    
    /**
     * Konstruktor mit Dependency Injection aller benötigten Services
     */
    public function __construct(
        CsvFileReader $csvFileReader,
        UserValidator $userValidator,
        RequestStack $requestStack
    ) {
        $this->csvFileReader = $csvFileReader;
        $this->userValidator = $userValidator;
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
            // CSV-Datei öffnen und Header lesen
            $handle = $this->csvFileReader->openCsvFile($file);
            $header = $this->csvFileReader->readHeader($handle);
            $columnIndices = $this->csvFileReader->validateRequiredColumns($header, self::REQUIRED_COLUMNS);
            
            // Daten zur Ticketverarbeitung
            $validTickets = [];
            $invalidRows = [];
            $uniqueUsernames = [];
            
            // Zeilen verarbeiten
            $this->csvFileReader->processRows($handle, function ($row, $rowNumber) use (
                &$validTickets,
                &$invalidRows,
                &$uniqueUsernames,
                $columnIndices
            ) {
                if (!$this->isRowValid($row, $columnIndices)) {
                    $invalidRows[] = [
                        'rowNumber' => $rowNumber,
                        'data' => $row
                    ];
                    return;
                }
                
                $username = $row[$columnIndices['username']];
                $uniqueUsernames[$username] = true;
                
                $validTickets[] = $this->createTicketFromRow($row, $columnIndices);
            });
            
            $result['validTickets'] = $validTickets;
            $result['invalidRows'] = $invalidRows;
            $result['unknownUsers'] = $this->userValidator->identifyUnknownUsers($uniqueUsernames);
            
            // Speichere die gültigen Tickets in der Session für späteren Zugriff
            $this->storeTicketsInSession($validTickets);
            
        } finally {
            // Datei-Handle sicher schließen
            $this->csvFileReader->closeHandle($handle);
        }
        
        return $result;
    }
    
    /**
     * Prüft, ob eine Zeile alle erforderlichen Werte enthält
     * 
     * @param array $row Die zu prüfende Zeile
     * @param array $columnIndices Die Indizes der benötigten Spalten
     * @return bool True, wenn die Zeile gültig ist, sonst False
     */
    private function isRowValid(array $row, array $columnIndices): bool
    {
        return isset($row[$columnIndices['ticketId']]) && !empty($row[$columnIndices['ticketId']]) &&
               isset($row[$columnIndices['username']]) && !empty($row[$columnIndices['username']]) &&
               isset($row[$columnIndices['ticketName']]);
    }
    
    /**
     * Erstellt ein Ticket-Array aus einer CSV-Zeile
     * 
     * @param array $row Die CSV-Zeile
     * @param array $columnIndices Die Indizes der benötigten Spalten
     * @return array Das Ticket als assoziatives Array
     */
    private function createTicketFromRow(array $row, array $columnIndices): array
    {
        return [
            'ticketId' => $row[$columnIndices['ticketId']],
            'username' => $row[$columnIndices['username']],
            'ticketName' => $row[$columnIndices['ticketName']] ?: null,
        ];
    }
    
    /**
     * Speichert die gültigen Tickets in der Session
     * 
     * @param array $validTickets Die zu speichernden gültigen Tickets
     */
    private function storeTicketsInSession(array $validTickets): void
    {
        $session = $this->requestStack->getSession();
        // Wenn keine Session vorhanden ist, nicht abst51rzen (z.B. in manchen CLI- oder Test-Umgebungen)
        if ($session === null) {
            return;
        }

        $session->set('valid_tickets', $validTickets);
    }
}