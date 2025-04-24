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

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CsvProcessor
{
    /**
     * Das User Repository zum Abrufen von Benutzerinformationen
     * @var UserRepository
     */
    private $userRepository;
    
    /**
     * Der Request-Stack für Zugriff auf die Session
     * @var RequestStack
     */
    private $requestStack;
    
    /**
     * Der Parameter-Bag für Zugriff auf Konfigurationswerte
     * @var ParameterBagInterface
     */
    private $params;
    
    /**
     * Konstruktor mit Dependency Injection aller benötigten Services
     * 
     * @param UserRepository $userRepository Das User Repository
     * @param RequestStack $requestStack Der Request-Stack für Session-Zugriff
     * @param ParameterBagInterface $params Der Parameter-Bag für Konfigurationswerte
     */
    public function __construct(
        UserRepository $userRepository,
        RequestStack $requestStack,
        ParameterBagInterface $params
    ) {
        $this->userRepository = $userRepository;
        $this->requestStack = $requestStack;
        $this->params = $params;
    }
    
    /**
     * Verarbeitet eine hochgeladene CSV-Datei mit Ticket-Daten
     * 
     * Diese Methode öffnet die CSV-Datei, validiert die Inhalte,
     * identifiziert gültige Tickets und unbekannte Benutzer. Die
     * gültigen Tickets werden in der Session für späteren Zugriff gespeichert.
     * 
     * @param UploadedFile $file Die hochgeladene CSV-Datei
     * @return array Strukturierte Ergebnisse der Verarbeitung (gültige Tickets, ungültige Zeilen, unbekannte Benutzer)
     * @throws \Exception Wenn die CSV-Datei nicht die erforderlichen Spalten enthält
     */
    public function process(UploadedFile $file): array
    {
        $result = [
            'validTickets' => [],
            'invalidRows' => [],
            'unknownUsers' => []
        ];
        
        // CSV-Datei öffnen
        $handle = fopen($file->getPathname(), 'r');
        
        // Erste Zeile für Header lesen
        $header = fgetcsv($handle, 1000, ',');
        
        // Indizes für die benötigten Spalten finden
        $ticketIdIndex = array_search('ticketId', $header);
        $usernameIndex = array_search('username', $header);
        $ticketNameIndex = array_search('ticketName', $header);
        
        // Prüfen, ob alle benötigten Spalten gefunden wurden
        if ($ticketIdIndex === false || $usernameIndex === false || $ticketNameIndex === false) {
            throw new \Exception('CSV-Datei enthält nicht alle erforderlichen Spalten (ticketId, username, ticketName)');
        }
        
        // CSV-Zeilen verarbeiten
        $rowNumber = 1; // Header ist Zeile 1
        $uniqueUsernames = [];
        
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $rowNumber++;
            
            // Prüfen, ob die Zeile alle erforderlichen Werte enthält
            if (
                !isset($row[$ticketIdIndex]) || empty($row[$ticketIdIndex]) ||
                !isset($row[$usernameIndex]) || empty($row[$usernameIndex]) ||
                !isset($row[$ticketNameIndex])
            ) {
                $result['invalidRows'][] = [
                    'rowNumber' => $rowNumber,
                    'data' => $row
                ];
                continue;
            }
            
            // Benutzernamen für spätere Überprüfung sammeln
            $username = $row[$usernameIndex];
            $uniqueUsernames[$username] = true;
            
            // Gültiges Ticket speichern
            $result['validTickets'][] = [
                'ticketId' => $row[$ticketIdIndex],
                'username' => $username,
                'ticketName' => $row[$ticketNameIndex] ?: null,
            ];
        }
        
        fclose($handle);
        
        // Prüfen, welche Benutzer keine bekannten E-Mail-Adressen haben
        if (!empty($uniqueUsernames)) {
            // Alle benötigten Benutzer auf einmal aus der Datenbank laden
            $users = $this->userRepository->findMultipleByUsernames(array_keys($uniqueUsernames));
            $knownUsernames = [];
            
            // Liste der bekannten Benutzernamen erstellen
            foreach ($users as $user) {
                $knownUsernames[$user->getUsername()] = true;
            }
            
            // Unbekannte Benutzer identifizieren
            foreach (array_keys($uniqueUsernames) as $username) {
                if (!isset($knownUsernames[$username])) {
                    $result['unknownUsers'][] = $username;
                }
            }
        }
        
        // Speichere die gültigen Tickets in der Session für späteren Zugriff
        $this->requestStack->getSession()->set('valid_tickets', $result['validTickets']);
        
        return $result;
    }
}