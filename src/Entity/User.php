<?php
/**
 * User.php
 * 
 * Diese Entitätsklasse repräsentiert einen Benutzer im System.
 * Jeder Benutzer hat einen eindeutigen Benutzernamen und eine E-Mail-Adresse,
 * an die Ticket-Benachrichtigungen gesendet werden können.
 * 
 * @package App\Entity
 */

namespace App\Entity;

use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * User-Entität zur Speicherung von Benutzerinformationen
 * 
 * Der Benutzername muss eindeutig sein (UniqueEntity-Constraint)
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity('username')]
class User
{
    /**
     * Eindeutige ID des Benutzers
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Benutzername (eindeutig im System)
     * Wird verwendet, um Benutzer in der CSV-Datei zu identifizieren
     */
    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    private ?string $username = null;

    /**
     * E-Mail-Adresse des Benutzers
     * An diese Adresse werden die Ticket-Benachrichtigungen gesendet
     */
    #[ORM\Column(type: 'email_address')]
    #[Assert\NotBlank]
    private ?EmailAddress $email = null;

    /**
     * Gibt an, ob der Benutzer von Umfragen ausgeschlossen ist
     */
    #[ORM\Column]
    private bool $excludedFromSurveys = false;

    /**
     * Gibt die ID des Benutzers zurück
     * 
     * @return int|null Die ID des Benutzers
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt den Benutzernamen zurück
     * 
     * @return string|null Der Benutzername
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Setzt den Benutzernamen
     * 
     * @param string $username Der Benutzername
     * @return self Für Method-Chaining
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Gibt die E-Mail-Adresse des Benutzers zurück
     * 
     * @return EmailAddress|null Die E-Mail-Adresse
     */
    public function getEmail(): ?EmailAddress
    {
        return $this->email;
    }

    /**
     * Setzt die E-Mail-Adresse des Benutzers
     * 
     * @param EmailAddress|string $email Die E-Mail-Adresse
     * @return self Für Method-Chaining
     */
    public function setEmail(EmailAddress|string $email): self
    {
        if (is_string($email)) {
            $this->email = EmailAddress::fromString($email);
        } else {
            $this->email = $email;
        }

        return $this;
    }

    /**
     * Gibt zurück, ob der Benutzer von Umfragen ausgeschlossen ist
     */
    public function isExcludedFromSurveys(): bool
    {
        return $this->excludedFromSurveys;
    }

    /**
     * Setzt, ob der Benutzer von Umfragen ausgeschlossen ist
     */
    public function setExcludedFromSurveys(bool $excludedFromSurveys): self
    {
        $this->excludedFromSurveys = $excludedFromSurveys;

        return $this;
    }
}