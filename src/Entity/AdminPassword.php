<?php

namespace App\Entity;

use App\Repository\AdminPasswordRepository;
use App\ValueObject\SecurePassword;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity zur Verwaltung des Administrator-Passworts.
 * 
 * Diese Klasse speichert das verschlüsselte Administrator-Passwort in der Datenbank
 * und stellt Methoden zur Verfügung, um das Passwort zu setzen und abzurufen.
 */
#[ORM\Entity(repositoryClass: AdminPasswordRepository::class)]
#[ORM\Table(name: 'admin_password')]
class AdminPassword
{
    /**
     * Einzigartige ID des Passworteintrags.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Das sichere Passwort als Value Object
     */
    #[ORM\Column(type: 'secure_password')]
    private ?SecurePassword $password = null;

    /**
     * Gibt die ID des Passworteintrags zurück.
     *
     * @return int|null Die ID oder null, wenn nicht gesetzt
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt das sichere Passwort zurück.
     *
     * @return SecurePassword|null Das sichere Passwort
     */
    public function getPassword(): ?SecurePassword
    {
        return $this->password;
    }

    /**
     * Setzt das Passwort von einem Klartext-Passwort.
     *
     * @param string $plainPassword Das Klartext-Passwort
     * @return self Instanz dieser Klasse für Method Chaining
     */
    public function setPasswordFromPlaintext(string $plainPassword): self
    {
        $this->password = SecurePassword::fromPlaintext($plainPassword);

        return $this;
    }

    /**
     * Setzt das Passwort von einem Hash.
     *
     * @param string $hash Der Passwort-Hash
     * @return self Instanz dieser Klasse für Method Chaining
     */
    public function setPasswordFromHash(string $hash): self
    {
        $this->password = SecurePassword::fromHash($hash);

        return $this;
    }

    /**
     * Überprüft ein Klartext-Passwort gegen das gespeicherte Passwort.
     *
     * @param string $plainPassword Das zu überprüfende Klartext-Passwort
     * @return bool True wenn das Passwort übereinstimmt
     */
    public function verifyPassword(string $plainPassword): bool
    {
        return $this->password?->verify($plainPassword) ?? false;
    }

    /**
     * Überprüft, ob das Passwort ein Rehashing benötigt.
     *
     * @return bool True wenn das Passwort ein Rehashing benötigt
     */
    public function needsPasswordRehash(): bool
    {
        return $this->password?->needsRehash() ?? false;
    }
}