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
     * Findet einen Benutzer anhand seines Benutzernamens
     * 
     * @param string $username Der gesuchte Benutzername
     * @return User|null Der gefundene Benutzer oder null, wenn kein passender Benutzer gefunden wurde
     */
    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }
    
    /**
     * Findet mehrere Benutzer anhand ihrer Benutzernamen
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
        return $this->createQueryBuilder('u')
            ->where('u.username IN (:usernames)')
            ->setParameter('usernames', $usernames)
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
}