<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmailTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity für versionierte E-Mail-Templates.
 *
 * Jedes Template hat ein Gültigkeitsdatum (validFrom), ab dem es für
 * neue Tickets verwendet wird. Beim E-Mail-Versand wird anhand des
 * Ticket-Erstelldatums das passende Template ausgewählt.
 */
#[ORM\Entity(repositoryClass: EmailTemplateRepository::class)]
#[ORM\Table(name: 'email_templates')]
#[ORM\HasLifecycleCallbacks]
class EmailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $validFrom;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    /**
     * Initialisiert Datums-Felder mit dem aktuellen Zeitpunkt.
     */
    public function __construct()
    {
        $this->validFrom = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Gibt die ID des Templates zurück (null wenn noch nicht persistiert).
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt den Anzeigenamen des Templates zurück.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Setzt den Anzeigenamen des Templates.
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Gibt den HTML-Inhalt des Templates zurück.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Setzt den HTML-Inhalt des Templates (mit Platzhaltern wie {{username}}).
     */
    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Gibt das Datum zurück, ab dem dieses Template für neue Tickets gilt.
     */
    public function getValidFrom(): \DateTimeInterface
    {
        return $this->validFrom;
    }

    /**
     * Setzt das Gültig-Ab-Datum des Templates.
     */
    public function setValidFrom(\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    /**
     * Gibt den Erstellungszeitpunkt des Templates zurück.
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Gibt den Zeitpunkt der letzten Aktualisierung zurück.
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Doctrine Lifecycle-Callback: Aktualisiert den updatedAt-Zeitstempel vor jedem Update.
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
