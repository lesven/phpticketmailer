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
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Erstellt einen neuen Benutzer mit den angegebenen Daten
     * 
     * @param string $username Benutzername
     * @param string $email E-Mail-Adresse
     * @return User Der erstellte Benutzer
     * @throws \InvalidArgumentException Bei ungültigen Daten
     */
    public function createUser(string $username, string $email): User
    {
        try {
            // 🎯 DDD: Verwende Domain Factory Method statt direkter Konstruktion
            $user = User::create($username, $email);

            $this->entityManager->persist($user);

            return $user;
        } catch (InvalidUsernameException $e) {
            throw new \InvalidArgumentException("Ungültiger Benutzername: {$username} - " . $e->getMessage());
        } catch (InvalidEmailAddressException $e) {
            throw new \InvalidArgumentException("Ungültige E-Mail-Adresse: {$email} - " . $e->getMessage());
        }
    }

    /**
     * Persistiert alle erstellten Benutzer in der Datenbank
     */
    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
