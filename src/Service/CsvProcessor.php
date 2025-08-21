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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

class CsvProcessor
{
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
        $requiredColumns = array_values($fieldMapping);
        
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
                
                $username = $row[$columnIndices[$fieldMapping['username']]];
                $uniqueUsernames[$username] = true;
                
                $validTickets[] = $this->createTicketFromRow($row, $columnIndices, $fieldMapping);
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
     * @param array $fieldMapping Die Zuordnung der logischen zu physischen Feldnamen
     * @return bool True, wenn die Zeile gültig ist, sonst False
     */
    private function isRowValid(array $row, array $columnIndices, array $fieldMapping): bool
    {
        return isset($row[$columnIndices[$fieldMapping['ticketId']]]) && !empty($row[$columnIndices[$fieldMapping['ticketId']]]) &&
               isset($row[$columnIndices[$fieldMapping['username']]]) && !empty($row[$columnIndices[$fieldMapping['username']]]) &&
               isset($row[$columnIndices[$fieldMapping['ticketName']]]);
    }
    
    /**
     * Erstellt ein Ticket-Array aus einer CSV-Zeile
     * 
     * @param array $row Die CSV-Zeile
     * @param array $columnIndices Die Indizes der benötigten Spalten
     * @param array $fieldMapping Die Zuordnung der logischen zu physischen Feldnamen
     * @return array Das Ticket als assoziatives Array
     */
    private function createTicketFromRow(array $row, array $columnIndices, array $fieldMapping): array
    {
        $ticketNameRaw = $row[$columnIndices[$fieldMapping['ticketName']]] ?? '';
        $ticketName = mb_substr($ticketNameRaw, 0, 50);
        return [
            'ticketId' => $row[$columnIndices[$fieldMapping['ticketId']]],
            'username' => $row[$columnIndices[$fieldMapping['username']]],
            'ticketName' => $ticketName ?: null,
        ];
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