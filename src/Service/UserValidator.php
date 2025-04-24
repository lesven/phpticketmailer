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
            $knownUsernames[$user->getUsername()] = true;
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
        return $this->userRepository->findOneByUsername($username) !== null;
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
}