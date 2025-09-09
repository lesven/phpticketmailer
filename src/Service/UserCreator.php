<?php

namespace App\Service;

use App\Entity\User;
use App\ValueObject\EmailAddress;
use App\ValueObject\Username;
use App\Exception\InvalidEmailAddressException;
use App\Exception\InvalidUsernameException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service zur Erstellung neuer Benutzer-Entitäten
 * 
 * Kapselt die Logik für das Erstellen und Persistieren von Benutzern
 * und sorgt für einheitliche Validierung und Behandlung.
 */
class UserCreator
{
    private array $newUsers = [];    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Erstellt einen neuen Benutzer mit den angegebenen Daten
     * 
     * @param string $username Benutzername
     * @param string $email E-Mail-Adresse
     * @throws \InvalidArgumentException Bei ungültigen Daten
     */
    public function createUser(string $username, string $email): void
    {
        try {
            // 🎯 DDD: Verwende Domain Factory Method statt direkter Konstruktion
            $user = User::create($username, $email);

            $this->entityManager->persist($user);
            $this->newUsers[] = $user;
            
        } catch (InvalidUsernameException $e) {
            throw new \InvalidArgumentException("Ungültiger Benutzername: {$username} - " . $e->getMessage());
        } catch (InvalidEmailAddressException $e) {
            throw new \InvalidArgumentException("Ungültige E-Mail-Adresse: {$email} - " . $e->getMessage());
        }
    }

    /**
     * Persistiert alle erstellten Benutzer in der Datenbank
     * 
     * @return int Anzahl der persistierten Benutzer
     */
    public function persistUsers(): int
    {
        if (empty($this->newUsers)) {
            return 0;
        }

        $this->entityManager->flush();
        $count = count($this->newUsers);
        
        // Array zurücksetzen für wiederholte Verwendung
        $this->newUsers = [];
        
        return $count;
    }

    /**
     * Gibt die Anzahl der erstellten, aber noch nicht persistierten Benutzer zurück
     */
    public function getPendingUsersCount(): int
    {
        return count($this->newUsers);
    }
}
