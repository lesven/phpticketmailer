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
use App\ValueObject\Username;
use App\Exception\InvalidEmailAddressException;
use App\Exception\InvalidUsernameException;
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
    #[ORM\Column(type: 'username', unique: true)]
    #[Assert\NotBlank]
    private ?Username $username = null;

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
     * @return Username|null Der Benutzername
     */
    public function getUsername(): ?Username
    {
        return $this->username;
    }

    /**
     * Setzt den Benutzernamen
     * 
     * @param Username|string $username Der Benutzername
     * @return self Für Method-Chaining
     */
    public function setUsername(Username|string $username): self
    {
        if (is_string($username)) {
            $this->username = Username::fromString($username);
        } else {
            $this->username = $username;
        }

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

    // ========================================
    // 🏗️ DOMAIN LOGIC (DDD Rich Model)
    // ========================================

    /**
     * Factory Method: Erstellt einen neuen User mit Validierung
     * 
     * @param string|Username $username Der Benutzername
     * @param string|EmailAddress $email Die E-Mail-Adresse
     * @return self Neue User-Instanz
     * @throws InvalidEmailAddressException|InvalidUsernameException Bei ungültigen Daten
     */
    public static function create(string|Username $username, string|EmailAddress $email): self
    {
        $user = new self();
        $user->setUsername($username);
        $user->setEmail($email);
        
        return $user;
    }

    /**
     * Geschäftsregel: Prüft ob der Benutzer für E-Mail-Versand berechtigt ist
     * 
     * @return bool True wenn E-Mails versendet werden dürfen
     */
    public function isEligibleForEmailNotifications(): bool
    {
        return !$this->excludedFromSurveys && $this->email !== null;
    }

    /**
     * Geschäftsregel: Schließt den Benutzer von Umfragen aus
     * 
     * @param string|null $reason Optionaler Grund für den Ausschluss
     * @return self Für Method-Chaining
     */
    public function excludeFromSurveys(?string $reason = null): self
    {
        $this->excludedFromSurveys = true;
        // TODO: Später können wir excludedReason und excludedAt Felder hinzufügen
        
        return $this;
    }

    /**
     * Geschäftsregel: Inkludiert den Benutzer wieder in Umfragen
     * 
     * @return self Für Method-Chaining
     */
    public function includeInSurveys(): self
    {
        $this->excludedFromSurveys = false;
        
        return $this;
    }

    /**
     * Geschäftsregel: Aktualisiert die E-Mail-Adresse mit Geschäftslogik
     * 
     * @param string|EmailAddress $newEmail Die neue E-Mail-Adresse
     * @return self Für Method-Chaining
     */
    public function updateEmail(string|EmailAddress $newEmail): self
    {
        // Normalisiere zu EmailAddress Value Object
        $emailAddress = is_string($newEmail) ? EmailAddress::fromString($newEmail) : $newEmail;
        
        // Geschäftsregel: Keine Änderung wenn E-Mail gleich ist
        if ($this->email && $this->email->equals($emailAddress)) {
            return $this; // Keine Änderung nötig
        }
        
        $this->email = $emailAddress;
        
        return $this;
    }

    /**
     * Geschäftsregel: Aktualisiert den Benutzernamen mit Geschäftslogik
     * 
     * @param string|Username $newUsername Der neue Benutzername
     * @return self Für Method-Chaining
     */
    public function updateUsername(string|Username $newUsername): self
    {
        // Normalisiere zu Username Value Object
        $usernameObj = is_string($newUsername) ? Username::fromString($newUsername) : $newUsername;
        
        // Geschäftsregel: Keine Änderung wenn Username gleich ist
        if ($this->username && $this->username->equals($usernameObj)) {
            return $this; // Keine Änderung nötig
        }
        
        $this->username = $usernameObj;
        
        return $this;
    }

    /**
     * Geschäftsregel: Prüft ob der User den gleichen Username hat (case-insensitive)
     * 
     * @param string|Username $username Der zu vergleichende Username
     * @return bool True wenn Username übereinstimmt
     */
    public function hasUsername(string|Username $username): bool
    {
        if ($this->username === null) {
            return false;
        }
        
        $usernameObj = is_string($username) ? Username::fromString($username) : $username;
        
        return $this->username->equals($usernameObj);
    }

    /**
     * Geschäftsregel: Prüft ob der User die gleiche E-Mail hat
     * 
     * @param string|EmailAddress $email Die zu vergleichende E-Mail
     * @return bool True wenn E-Mail übereinstimmt
     */
    public function hasEmail(string|EmailAddress $email): bool
    {
        if ($this->email === null) {
            return false;
        }
        
        $emailObj = is_string($email) ? EmailAddress::fromString($email) : $email;
        
        return $this->email->equals($emailObj);
    }

    /**
     * Domain-Information: Gibt eine String-Repräsentation des Users zurück
     * 
     * @return string Benutzer-Information
     */
    public function __toString(): string
    {
        $username = $this->username ? $this->username->getValue() : 'N/A';
        $email = $this->email ? $this->email->getValue() : 'N/A';
        
        return "User[{$username}, {$email}]";
    }
}