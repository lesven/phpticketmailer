<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CsvProcessor
{
    private $userRepository;
    private $requestStack;
    private $params;
    
    public function __construct(
        UserRepository $userRepository,
        RequestStack $requestStack,
        ParameterBagInterface $params
    ) {
        $this->userRepository = $userRepository;
        $this->requestStack = $requestStack;
        $this->params = $params;
    }
    
    public function process(UploadedFile $file): array
    {
        $result = [
            'validTickets' => [],
            'invalidRows' => [],
            'unknownUsers' => []
        ];
        
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
            
            $username = $row[$usernameIndex];
            $uniqueUsernames[$username] = true;
            
            $result['validTickets'][] = [
                'ticketId' => $row[$ticketIdIndex],
                'username' => $username,
                'ticketName' => $row[$ticketNameIndex] ?: null,
            ];
        }
        
        fclose($handle);
        
        // Prüfen, welche Benutzer keine bekannten E-Mail-Adressen haben
        if (!empty($uniqueUsernames)) {
            $users = $this->userRepository->findMultipleByUsernames(array_keys($uniqueUsernames));
            $knownUsernames = [];
            
            foreach ($users as $user) {
                $knownUsernames[$user->getUsername()] = true;
            }
            
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