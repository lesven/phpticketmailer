<?php

namespace App\Service;

use App\Entity\AdminPassword;
use App\Exception\WeakPasswordException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service für die Authentifizierung und Passwortverwaltung
 * 
 * Kapselt die gesamte Logik für Login, Passwortprüfung und -änderung.
 * Der SecurityController delegiert alle Geschäftsoperationen hierher.
 */
class AuthenticationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Verifiziert ein Passwort und gibt zurück, ob die Authentifizierung erfolgreich war.
     * 
     * Erstellt beim ersten Start automatisch ein Standard-Admin-Passwort.
     *
     * @param string $password Das zu prüfende Passwort
     * @return bool true wenn Authentifizierung erfolgreich
     */
    public function authenticate(string $password): bool
    {
        $adminPassword = $this->getOrCreateAdminPassword();

        return $adminPassword->verifyPassword($password);
    }

    /**
     * Ändert das Admin-Passwort nach Prüfung des aktuellen Passworts.
     *
     * @param string $currentPassword Das aktuelle Passwort zur Verifizierung
     * @param string $newPassword Das neue Passwort
     * @return bool true wenn Änderung erfolgreich
     * @throws WeakPasswordException Wenn das neue Passwort zu schwach ist
     */
    public function changePassword(string $currentPassword, string $newPassword): bool
    {
        $adminPassword = $this->entityManager
            ->getRepository(AdminPassword::class)
            ->findOneBy([], ['id' => 'ASC']);

        if (!$adminPassword || !$adminPassword->verifyPassword($currentPassword)) {
            return false;
        }

        // SecurePassword validiert automatisch die Stärke — wirft WeakPasswordException
        $adminPassword->setPasswordFromPlaintext($newPassword);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Gibt das vorhandene AdminPassword zurück oder erstellt eines mit Standardpasswort.
     */
    private function getOrCreateAdminPassword(): AdminPassword
    {
        $adminPassword = $this->entityManager
            ->getRepository(AdminPassword::class)
            ->findOneBy([], ['id' => 'ASC']);

        if ($adminPassword !== null) {
            return $adminPassword;
        }

        $adminPassword = new AdminPassword();
        $adminPassword->setPasswordFromPlaintext('DefaultP@ssw0rd123!');
        $this->entityManager->persist($adminPassword);
        $this->entityManager->flush();

        return $adminPassword;
    }
}
