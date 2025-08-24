<?php
/**
 * UserValidator.php
 * 
 * Diese Klasse ist verantwortlich für die Validierung von Benutzern.
 * Sie prüft, ob Benutzer in der Datenbank existieren und bietet
 * Funktionen zum Filtern bekannter und unbekannter Benutzer.
 * 
 * @package App\Service
 */

namespace App\Service;

use App\Repository\UserRepository;

class UserValidator
{
    /**
     * @var UserRepository
     */
    private UserRepository $userRepository;
    
    /**
     * Konstruktor mit Dependency Injection des User Repositories
     * 
     * @param UserRepository $userRepository Das User Repository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    
    /**
     * Identifiziert unbekannte Benutzer anhand der Benutzernamen
     * 
     * @param array $usernames Liste der zu prüfenden Benutzernamen
     * @return array Liste der unbekannten Benutzernamen
     */
    public function identifyUnknownUsers(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }
        
        $users = $this->userRepository->findMultipleByUsernames(array_keys($usernames));
        $knownUsernames = [];
        
        foreach ($users as $user) {
            $knownUsernames[(string) $user->getUsername()] = true;
        }
        
        $unknownUsers = [];
        foreach (array_keys($usernames) as $username) {
            if (!isset($knownUsernames[$username])) {
                $unknownUsers[] = $username;
            }
        }
        
        return $unknownUsers;
    }
    
    /**
     * Prüft, ob ein Benutzername in der Datenbank existiert
     * 
     * @param string $username Der zu prüfende Benutzername
     * @return bool True, wenn der Benutzer existiert, sonst False
     */
    public function isKnownUser(string $username): bool
    {
        return $this->userRepository->findByUsername($username) !== null;
    }
    
    /**
     * Filtert eine Liste von Benutzer-Datensätzen nach bekannten und unbekannten Benutzern
     * 
     * @param array $records Array mit Benutzer-Datensätzen, jeder mit einem "username"-Schlüssel
     * @return array Zwei Arrays: [knownUserRecords, unknownUserRecords]
     */
    public function filterKnownAndUnknownUsers(array $records): array
    {
        $knownUsers = [];
        $unknownUsers = [];
        
        foreach ($records as $record) {
            if (!isset($record['username'])) {
                continue;
            }
            
            if ($this->isKnownUser($record['username'])) {
                $knownUsers[] = $record;
            } else {
                $unknownUsers[] = $record;
            }
        }
        
        return [$knownUsers, $unknownUsers];
    }
    
    /**
     * Validiert einen Benutzernamen nach definierten Regeln
     * 
     * @param string $username Der zu validierende Benutzername
     * @return bool True, wenn der Benutzername gültig ist
     */
    public function isValidUsername(string $username): bool
    {
        // Benutzername darf nicht leer sein
        if (empty(trim($username))) {
            return false;
        }
        
        // Länge zwischen 2 und 50 Zeichen
        $length = strlen($username);
        if ($length < 2 || $length > 50) {
            return false;
        }
        
        // Nur alphanumerische Zeichen, Punkt, Unterstrich, Bindestrich und @ erlaubt
        if (!preg_match('/^[a-zA-Z0-9._@-]+$/', $username)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validiert eine E-Mail-Adresse
     * 
     * @param string $email Die zu validierende E-Mail-Adresse
     * @return bool True, wenn die E-Mail-Adresse gültig ist
     */
    public function isValidEmail(string $email): bool
    {
        // Grundlegende PHP-Filter-Validierung
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Maximallänge prüfen
        if (strlen($email) > 254) {
            return false;
        }
        
        return true;
    }
}