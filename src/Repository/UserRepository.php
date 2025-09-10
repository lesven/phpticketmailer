<?php
/**
 * UserRepository.php
 * 
 * Diese Repository-Klasse stellt Methoden zum Zugriff auf User-Entitäten bereit.
 * Sie erweitert den ServiceEntityRepository von Doctrine und bietet spezielle
 * Suchmethoden und Abfragen für die Arbeit mit Benutzerdaten.
 * 
 * @package App\Repository
 */

namespace App\Repository;

use App\Entity\User;
use App\ValueObject\Username;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für die User-Entität
 * 
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    /**
     * Konstruktor mit Doctrine ManagerRegistry als Dependency
     * 
     * @param ManagerRegistry $registry Die Doctrine ManagerRegistry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Findet einen Benutzer anhand seines Benutzernamens (case-insensitive mit Username Value Object)
     * 
     * @param string $username Der gesuchte Benutzername
     * @return User|null Der gefundene Benutzer oder null, wenn kein passender Benutzer gefunden wurde
     */
    public function findByUsername(string $username): ?User
    {
        // Nutze Username Value Object für konsistente Normalisierung
        $usernameObj = Username::fromString($username);
        
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.username) = :username')
            ->setParameter('username', (string) $usernameObj)
            ->getQuery()
            ->getOneOrNullResult();
    }
    
    /**
     * Findet mehrere Benutzer anhand ihrer Benutzernamen (case-insensitive mit Username Value Objects)
     * 
     * Diese Methode ist optimiert, um mehrere Benutzer gleichzeitig zu suchen,
     * anstatt mehrere einzelne Datenbankabfragen durchzuführen. Dies ist besonders
     * nützlich bei der Verarbeitung von CSV-Dateien mit vielen Benutzern.
     * 
     * @param array $usernames Array mit Benutzernamen
     * @return User[] Array mit den gefundenen Benutzern
     */
    public function findMultipleByUsernames(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }
        
        // Normalisiere alle Benutzernamen mit Username Value Object
        $normalizedUsernames = array_map(function($username) {
            return (string) Username::fromString($username);
        }, $usernames);
        
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.username) IN (:usernames)')
            ->setParameter('usernames', $normalizedUsernames)
            ->getQuery()
            ->getResult();
    }
      /**
     * Sucht Benutzer anhand des Benutzernamens mit optionalem Sortieren
     * 
     * Diese Methode erlaubt eine Teiltext-Suche (case-insensitive) nach dem Benutzernamen
     * und unterstützt das Sortieren der Ergebnisse nach verschiedenen Feldern.
     * 
     * @param string|null $searchTerm Suchbegriff für den Benutzernamen (kann null sein für alle Benutzer)
     * @param string|null $sortField Feld für die Sortierung (id, username, email)
     * @param string|null $sortDirection Sortierrichtung (ASC oder DESC)
     * @return User[] Array mit den gefundenen Benutzern
     */
    public function searchByUsername(?string $searchTerm, ?string $sortField = null, ?string $sortDirection = null): array
    {
        // Validieren und Standardwerte für Sortierparameter setzen
        $validSortFields = ['id', 'username', 'email'];
        $sortField = in_array($sortField, $validSortFields) ? $sortField : 'id';
        $sortDirection = ($sortDirection === 'DESC') ? 'DESC' : 'ASC';
        
        $queryBuilder = $this->createQueryBuilder('u');
        
        // Suchfilter anwenden, wenn ein Suchbegriff angegeben ist
        if ($searchTerm) {
            $queryBuilder
                ->where('LOWER(u.username) LIKE LOWER(:searchTerm)')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }
        
        // Sortierung anwenden
        $queryBuilder->orderBy('u.' . $sortField, $sortDirection);
        
        return $queryBuilder->getQuery()->getResult();
    }
    
    /**
     * Findet Benutzer mit Paginierung und Sortierung
     * 
     * Diese Methode unterstützt die serverseitige Paginierung der Benutzerliste
     * mit optionaler Sortierung nach verschiedenen Feldern.
     * 
     * @param int $offset Anzahl der zu überspringenden Einträge
     * @param int $limit Maximale Anzahl der zurückzugebenden Einträge
     * @param string|null $sortField Feld für die Sortierung (id, username, email)
     * @param string|null $sortDirection Sortierrichtung (ASC oder DESC)
     * @return User[] Array mit den gefundenen Benutzern
     */
    public function findPaginated(int $offset, int $limit, ?string $sortField = null, ?string $sortDirection = null): array
    {
        // Validieren und Standardwerte für Sortierparameter setzen
        $validSortFields = ['id', 'username', 'email'];
        $sortField = in_array($sortField, $validSortFields) ? $sortField : 'id';
        $sortDirection = ($sortDirection === 'DESC') ? 'DESC' : 'ASC';
        
        return $this->createQueryBuilder('u')
            ->orderBy('u.' . $sortField, $sortDirection)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Erstellt einen QueryBuilder mit Sortierung für Paginierung
     * 
     * @param string $sortField Das Feld nach dem sortiert werden soll
     * @param string $sortDirection Die Sortierrichtung (ASC oder DESC)
     * @return \Doctrine\ORM\QueryBuilder QueryBuilder für die Paginierung
     */
    public function createSortedQueryBuilder(string $sortField = 'id', string $sortDirection = 'ASC'): \Doctrine\ORM\QueryBuilder
    {
        // Erlaubte Sortierfelder validieren
        $allowedFields = ['id', 'username', 'email'];
        if (!in_array($sortField, $allowedFields)) {
            $sortField = 'id';
        }
        
        // Sortierrichtung validieren
        $sortDirection = strtoupper($sortDirection);
        if (!in_array($sortDirection, ['ASC', 'DESC'])) {
            $sortDirection = 'ASC';
        }
        
        return $this->createQueryBuilder('u')
            ->orderBy('u.' . $sortField, $sortDirection);
    }

    /**
     * Identifiziert unbekannte Benutzer anhand der Benutzernamen
     * 
     * @param array $usernames Liste der zu prüfenden Benutzernamen (assoziatives Array)
     * @return array Liste der unbekannten Benutzernamen
     */
    public function identifyUnknownUsers(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }
        
        $users = $this->findMultipleByUsernames(array_keys($usernames));
        $foundUsernames = [];
        
        // Sammle alle gefundenen Username Value Objects
        foreach ($users as $user) {
            if ($user->getUsername()) {
                $foundUsernames[] = $user->getUsername();
            }
        }
        
        $unknownUsers = [];
        foreach (array_keys($usernames) as $csvUsername) {
            try {
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
            } catch (\App\Exception\InvalidUsernameException $e) {
                // Ungültige Benutzernamen werden stillschweigend ignoriert
                // Sie sollten bereits in der CSV-Verarbeitung als ungültig markiert worden sein
                error_log("Invalid username skipped in identifyUnknownUsers: '{$csvUsername}' - " . $e->getMessage());
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
    public function userExists(string $username): bool
    {
        return $this->findByUsername($username) !== null;
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
            
            if ($this->userExists($record['username'])) {
                $knownUsers[] = $record;
            } else {
                $unknownUsers[] = $record;
            }
        }
        
        return [$knownUsers, $unknownUsers];
    }
}