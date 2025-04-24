<?php
/**
 * EmailSent.php
 * 
 * Diese Entitätsklasse repräsentiert eine gesendete E-Mail im System.
 * Sie protokolliert Details zu jeder E-Mail, die über das Ticket-System versendet wurde,
 * einschließlich des Empfängers, des Status und ob die E-Mail im Testmodus gesendet wurde.
 * 
 * @package App\Entity
 */

namespace App\Entity;

use App\Repository\EmailSentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * EmailSent-Entität zur Protokollierung versendeter E-Mails
 */
#[ORM\Entity(repositoryClass: EmailSentRepository::class)]
#[ORM\Table(name: 'emails_sent')]
class EmailSent
{
    /**
     * Eindeutige ID der E-Mail-Protokollierung
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * ID des Tickets, für das die E-Mail versendet wurde
     */
    #[ORM\Column(length: 255)]
    private ?string $ticketId = null;

    /**
     * Benutzername des Empfängers
     */
    #[ORM\Column(length: 255)]
    private ?string $username = null;

    /**
     * E-Mail-Adresse des Empfängers
     */
    #[ORM\Column(length: 255)]
    private ?string $email = null;

    /**
     * Betreff der gesendeten E-Mail
     */
    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    /**
     * Status der E-Mail (z.B. 'sent', 'error: ...')
     */
    #[ORM\Column(length: 50)]
    private ?string $status = null;

    /**
     * Zeitpunkt, zu dem die E-Mail gesendet wurde
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $timestamp = null;

    /**
     * Gibt an, ob die E-Mail im Testmodus gesendet wurde
     */
    #[ORM\Column]
    private ?bool $testMode = null;

    /**
     * Optional: Name oder Beschreibung des Tickets
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ticketName = null;

    /**
     * Gibt die ID der E-Mail-Protokollierung zurück
     * 
     * @return int|null Die ID der Protokollierung
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt die Ticket-ID zurück
     * 
     * @return string|null Die ID des Tickets
     */
    public function getTicketId(): ?string
    {
        return $this->ticketId;
    }

    /**
     * Setzt die Ticket-ID
     * 
     * @param string $ticketId Die ID des Tickets
     * @return self Für Method-Chaining
     */
    public function setTicketId(string $ticketId): self
    {
        $this->ticketId = $ticketId;

        return $this;
    }

    /**
     * Gibt den Benutzernamen des Empfängers zurück
     * 
     * @return string|null Der Benutzername
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Setzt den Benutzernamen des Empfängers
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
     * Gibt die E-Mail-Adresse des Empfängers zurück
     * 
     * @return string|null Die E-Mail-Adresse
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Setzt die E-Mail-Adresse des Empfängers
     * 
     * @param string $email Die E-Mail-Adresse
     * @return self Für Method-Chaining
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Gibt den Betreff der E-Mail zurück
     * 
     * @return string|null Der Betreff
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * Setzt den Betreff der E-Mail
     * 
     * @param string $subject Der Betreff
     * @return self Für Method-Chaining
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Gibt den Status der E-Mail zurück
     * 
     * @return string|null Der Status (z.B. 'sent', 'error: ...')
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Setzt den Status der E-Mail
     * 
     * @param string $status Der Status
     * @return self Für Method-Chaining
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Gibt den Zeitstempel der E-Mail zurück
     * 
     * @return \DateTimeInterface|null Der Zeitstempel
     */
    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    /**
     * Setzt den Zeitstempel der E-Mail
     * 
     * @param \DateTimeInterface $timestamp Der Zeitstempel
     * @return self Für Method-Chaining
     */
    public function setTimestamp(\DateTimeInterface $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Formatiert den Zeitstempel als String
     * 
     * Hilfsmethode zur Anzeige des Zeitstempels in einem benutzerfreundlichen Format
     * 
     * @return string Der formatierte Zeitstempel (Format: YYYY-MM-DD HH:MM:SS)
     */
    public function getFormattedTimestamp(): string
    {
        return $this->timestamp ? $this->timestamp->format('Y-m-d H:i:s') : '';
    }

    /**
     * Gibt zurück, ob die E-Mail im Testmodus gesendet wurde
     * 
     * @return bool|null True, wenn die E-Mail im Testmodus gesendet wurde
     */
    public function getTestMode(): ?bool
    {
        return $this->testMode;
    }

    /**
     * Setzt den Testmodus-Status
     * 
     * @param bool $testMode True, wenn die E-Mail im Testmodus gesendet werden soll
     * @return self Für Method-Chaining
     */
    public function setTestMode(bool $testMode): self
    {
        $this->testMode = $testMode;

        return $this;
    }

    /**
     * Gibt den Namen oder die Beschreibung des Tickets zurück
     * 
     * @return string|null Der Name des Tickets
     */
    public function getTicketName(): ?string
    {
        return $this->ticketName;
    }

    /**
     * Setzt den Namen oder die Beschreibung des Tickets
     * 
     * @param string|null $ticketName Der Name des Tickets
     * @return self Für Method-Chaining
     */
    public function setTicketName(?string $ticketName): self
    {
        $this->ticketName = $ticketName;

        return $this;
    }
}