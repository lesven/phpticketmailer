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

use App\Exception\InvalidEmailAddressException;
use App\Exception\InvalidUsernameException;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use App\ValueObject\Username;

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
        $foundUsernames = [];
        
        // Sammle alle gefundenen Username Value Objects
        foreach ($users as $user) {
            if ($user->getUsername()) {
                $foundUsernames[] = $user->getUsername();
            }
        }
        
        $unknownUsers = [];
        foreach (array_keys($usernames) as $csvUsername) {
            $csvUsernameObj = Username::fromString($csvUsername);
            $isKnown = false;
            
            // Vergleiche mit allen gefundenen Benutzernamen über Value Object equals()
            foreach ($foundUsernames as $foundUsername) {
                if ($csvUsernameObj->equals($foundUsername)) {
                    $isKnown = true;
                    break;
                }
            }
            
            if (!$isKnown) {
                $unknownUsers[] = $csvUsername;
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
     * Diese Validierung ist weniger strikt als das Username Value Object
     * und erlaubt z.B. @ Zeichen für Email-basierte Usernames.
     * 
     * @param string $username Der zu validierende Benutzername
     * @return bool True, wenn der Benutzername gültig ist
     */
    public function isValidUsername(string $username): bool
    {
        // Nutze Username Value Object Normalisierung für konsistentes Trimming
        $trimmed = trim($username);
        
        // Benutzername darf nicht leer sein
        if (empty($trimmed)) {
            return false;
        }
        
        // Länge zwischen 2 und 50 Zeichen
        $length = strlen($trimmed);
        if ($length < 2 || $length > 50) {
            return false;
        }
        
        // Nur alphanumerische Zeichen, Punkt, Unterstrich, Bindestrich und @ erlaubt
        if (!preg_match('/^[a-zA-Z0-9._@-]+$/', $trimmed)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validiert eine E-Mail-Adresse
     * 
     * Behält die ursprüngliche, strengere Validierung bei,
     * um Breaking Changes zu vermeiden.
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
        
        // Maximallänge prüfen (wie EmailAddress Value Object)
        if (strlen($email) > 320) {
            return false;
        }
        
        return true;
    }
}