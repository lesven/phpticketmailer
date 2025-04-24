<?php
/**
 * SMTPConfig.php
 * 
 * Diese Entitätsklasse repräsentiert die SMTP-Konfiguration für den E-Mail-Versand.
 * Sie speichert alle notwendigen Einstellungen für die Verbindung zum SMTP-Server
 * sowie die Absender-Informationen für ausgehende E-Mails.
 * 
 * @package App\Entity
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * SMTPConfig-Entität zur Speicherung der E-Mail-Versand-Konfiguration
 */
#[ORM\Entity]
class SMTPConfig
{
    /**
     * Eindeutige ID der Konfiguration
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Hostname oder IP-Adresse des SMTP-Servers
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Der SMTP-Host darf nicht leer sein')]
    private ?string $host = null;

    /**
     * Port des SMTP-Servers (üblicherweise 25, 465 oder 587)
     */
    #[ORM\Column]
    #[Assert\NotBlank(message: 'Der SMTP-Port darf nicht leer sein')]
    #[Assert\Positive(message: 'Der Port muss eine positive Zahl sein')]
    private ?int $port = null;

    /**
     * Benutzername für die Authentifizierung am SMTP-Server (optional)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    /**
     * Passwort für die Authentifizierung am SMTP-Server (optional)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    /**
     * Gibt an, ob TLS-Verschlüsselung verwendet werden soll
     */
    #[ORM\Column]
    private bool $useTLS = false;

    /**
     * E-Mail-Adresse des Absenders
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Die Absender-E-Mail darf nicht leer sein')]
    #[Assert\Email(message: 'Die E-Mail-Adresse {{ value }} ist ungültig')]
    private ?string $senderEmail = null;

    /**
     * Name des Absenders, der in E-Mails angezeigt wird
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Der Absendername darf nicht leer sein')]
    private ?string $senderName = null;

    /**
     * Gibt die ID der SMTP-Konfiguration zurück
     * 
     * @return int|null Die ID der Konfiguration
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt den Hostnamen des SMTP-Servers zurück
     * 
     * @return string|null Der Hostname
     */
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * Setzt den Hostnamen des SMTP-Servers
     * 
     * @param string $host Der Hostname
     * @return self Für Method-Chaining
     */
    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Gibt den Port des SMTP-Servers zurück
     * 
     * @return int|null Der Port
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * Setzt den Port des SMTP-Servers
     * 
     * @param int $port Der Port
     * @return self Für Method-Chaining
     */
    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    /**
     * Gibt den Benutzernamen für die SMTP-Authentifizierung zurück
     * 
     * @return string|null Der Benutzername
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Setzt den Benutzernamen für die SMTP-Authentifizierung
     * 
     * @param string|null $username Der Benutzername
     * @return self Für Method-Chaining
     */
    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Gibt das Passwort für die SMTP-Authentifizierung zurück
     * 
     * @return string|null Das Passwort
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Setzt das Passwort für die SMTP-Authentifizierung
     * 
     * @param string|null $password Das Passwort
     * @return self Für Method-Chaining
     */
    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Gibt zurück, ob TLS-Verschlüsselung verwendet werden soll
     * 
     * @return bool True, wenn TLS verwendet werden soll
     */
    public function isUseTLS(): bool
    {
        return $this->useTLS;
    }

    /**
     * Setzt die Verwendung von TLS-Verschlüsselung
     * 
     * @param bool $useTLS True, wenn TLS verwendet werden soll
     * @return self Für Method-Chaining
     */
    public function setUseTLS(bool $useTLS): self
    {
        $this->useTLS = $useTLS;

        return $this;
    }

    /**
     * Gibt die E-Mail-Adresse des Absenders zurück
     * 
     * @return string|null Die E-Mail-Adresse
     */
    public function getSenderEmail(): ?string
    {
        return $this->senderEmail;
    }

    /**
     * Setzt die E-Mail-Adresse des Absenders
     * 
     * @param string $senderEmail Die E-Mail-Adresse
     * @return self Für Method-Chaining
     */
    public function setSenderEmail(string $senderEmail): self
    {
        $this->senderEmail = $senderEmail;

        return $this;
    }

    /**
     * Gibt den Namen des Absenders zurück
     * 
     * @return string|null Der Name des Absenders
     */
    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    /**
     * Setzt den Namen des Absenders
     * 
     * @param string $senderName Der Name des Absenders
     * @return self Für Method-Chaining
     */
    public function setSenderName(string $senderName): self
    {
        $this->senderName = $senderName;

        return $this;
    }

    /**
     * Generiert eine DSN (Data Source Name) für den Symfony Mailer
     * 
     * Diese Methode erstellt eine DSN im Format:
     * smtp://[username]:[password]@host:port[?encryption=tls]
     * 
     * @return string Die generierte DSN
     */
    public function getDSN(): string
    {
        $dsn = 'smtp://';
        
        if ($this->username && $this->password) {
            $dsn .= urlencode($this->username) . ':' . urlencode($this->password) . '@';
        }
        
        $dsn .= $this->host . ':' . $this->port;
        
        if ($this->useTLS) {
            $dsn .= '?encryption=tls';
        }
        
        return $dsn;
    }
}