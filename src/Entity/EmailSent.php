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
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketId;
use App\ValueObject\Username;
use App\ValueObject\TicketName;
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
    #[ORM\Column(type: 'ticket_id')]
    private ?TicketId $ticketId = null;

    /**
     * Benutzername des Empfängers
     */
    #[ORM\Column(type: 'username')]
    private ?Username $username = null;

    /**
     * E-Mail-Adresse des Empfängers
     */
    #[ORM\Column(type: 'email_address')]
    private ?EmailAddress $email = null;

    /**
     * Betreff der gesendeten E-Mail
     */
    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    /**
     * Status der E-Mail (z.B. 'sent', 'error: ...')
     */
    #[ORM\Column(type: 'email_status')]
    private ?EmailStatus $status = null;

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
    #[ORM\Column(type: 'ticket_name', length: 50, nullable: true)]
    private ?TicketName $ticketName = null;

    /**
     * Optional: Erstellungsdatum des Tickets (aus CSV)
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ticketCreated = null;

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
     * @return TicketId|null Die ID des Tickets
     */
    public function getTicketId(): ?TicketId
    {
        return $this->ticketId;
    }

    /**
     * Setzt die Ticket-ID
     * 
     * @param TicketId|string $ticketId Die ID des Tickets
     * @return self Für Method-Chaining
     */
    public function setTicketId(TicketId|string $ticketId): self
    {
        if (is_string($ticketId)) {
            $this->ticketId = TicketId::fromString($ticketId);
        } else {
            $this->ticketId = $ticketId;
        }

        return $this;
    }

    /**
     * Gibt den Benutzernamen des Empfängers zurück
     * 
     * @return Username|null Der Benutzername
     */
    public function getUsername(): ?Username
    {
        return $this->username;
    }

    /**
     * Setzt den Benutzernamen des Empfängers
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
     * Gibt die E-Mail-Adresse des Empfängers zurück
     * 
     * @return EmailAddress|null Die E-Mail-Adresse
     */
    public function getEmail(): ?EmailAddress
    {
        return $this->email;
    }

    /**
     * Setzt die E-Mail-Adresse des Empfängers
     * 
     * @param EmailAddress|string|null $email Die E-Mail-Adresse
     * @return self Für Method-Chaining
     */
    public function setEmail(EmailAddress|string|null $email): self
    {
        if (is_string($email)) {
            // Leere Strings werden als null behandelt
            if (empty($email)) {
                $this->email = null;
            } else {
                $this->email = EmailAddress::fromString($email);
            }
        } else {
            $this->email = $email;
        }

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
     * @return EmailStatus|null Der Status
     */
    public function getStatus(): ?EmailStatus
    {
        return $this->status;
    }

    /**
     * Setzt den Status der E-Mail
     * 
     * @param EmailStatus|string $status Der Status
     * @return self Für Method-Chaining
     */
    public function setStatus(EmailStatus|string $status): self
    {
        if (is_string($status)) {
            $status = EmailStatus::fromString($status);
        }

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
    public function getTicketName(): ?TicketName
    {
        return $this->ticketName;
    }

    /**
     * Setzt den Namen oder die Beschreibung des Tickets
     *
     * @param TicketName|string|null $ticketName Der Name des Tickets
     * @return self Für Method-Chaining
     */
    public function setTicketName(TicketName|string|null $ticketName): self
    {
        if (is_string($ticketName)) {
            $ticketName = trim($ticketName);
            $this->ticketName = $ticketName === '' ? null : TicketName::fromString($ticketName);
        } else {
            $this->ticketName = $ticketName;
        }

        return $this;
    }

    /**
     * Gibt das Erstellungsdatum des Tickets zurück
     *
     * @return string|null
     */
    public function getTicketCreated(): ?string
    {
        return $this->ticketCreated;
    }

    /**
     * Setzt das Erstellungsdatum des Tickets
     *
     * @param string|null $ticketCreated
     * @return self
     */
    public function setTicketCreated(?string $ticketCreated): self
    {
        $this->ticketCreated = $ticketCreated !== null && trim($ticketCreated) === '' ? null : $ticketCreated;

        return $this;
    }
}