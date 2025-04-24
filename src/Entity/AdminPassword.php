<?php

namespace App\Entity;

use App\Repository\AdminPasswordRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

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
    #[ORM\Column(type: 'integer')]
    private $id;

    /**
     * Das verschlüsselte Passwort, wie es in der Datenbank gespeichert wird.
     */
    #[ORM\Column(type: 'string', length: 255)]
    private $password;

    /**
     * Das unverschlüsselte Passwort (wird nicht in der Datenbank gespeichert).
     * Dieses Feld wird nur temporär verwendet, wenn ein neues Passwort gesetzt wird.
     */
    #[Assert\NotBlank(message: 'Das Passwort darf nicht leer sein')]
    #[Assert\Length(
        min: 8,
        minMessage: 'Das Passwort muss mindestens {{ limit }} Zeichen enthalten'
    )]
    private $plainPassword;

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
     * Gibt das verschlüsselte Passwort zurück.
     *
     * @return string|null Das verschlüsselte Passwort
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Setzt das verschlüsselte Passwort.
     *
     * @param string $password Das verschlüsselte Passwort
     * @return self Instanz dieser Klasse für Method Chaining
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }
    
    /**
     * Gibt das unverschlüsselte Passwort zurück.
     * Dieses wird nicht in der Datenbank gespeichert.
     *
     * @return string|null Das unverschlüsselte Passwort
     */
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }
    
    /**
     * Setzt das unverschlüsselte Passwort.
     * Dieses Feld wird nur temporär verwendet und nicht in der Datenbank gespeichert.
     *
     * @param string|null $plainPassword Das unverschlüsselte Passwort
     * @return self Instanz dieser Klasse für Method Chaining
     */
    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        
        return $this;
    }
}