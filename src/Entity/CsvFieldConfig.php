<?php
/**
 * CsvFieldConfig.php
 *
 * Diese Entity-Klasse repräsentiert die Konfiguration für CSV-Feld-Zuordnungen.
 * Sie speichert die Namen der CSV-Spalten, die den verschiedenen Datenfeldern
 * im System entsprechen (Ticket-ID, Benutzername, Ticket-Name).
 *
 * @package App\Entity
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity für die CSV-Feld-Konfiguration
 *
 * Diese Klasse definiert die Zuordnung zwischen CSV-Spalten und System-Feldern.
 * Sie ermöglicht es, flexible CSV-Formate zu unterstützen, indem die Spaltennamen
 * konfigurierbar sind.
 */
#[ORM\Entity]
#[ORM\Table(name: 'csv_field_config')]
class CsvFieldConfig
{
    /**
     * Standardwert für das Ticket-ID-Feld
     */
    public const DEFAULT_TICKET_ID_FIELD = 'Vorgangsschlüssel';
    
    /**
     * Standardwert für das Benutzername-Feld
     */
    public const DEFAULT_USERNAME_FIELD = 'Autor';
    
    /**
     * Standardwert für das Ticket-Name-Feld
     */
    public const DEFAULT_TICKET_NAME_FIELD = 'Zusammenfassung';

    /**
     * Standardwert für das Erstellungsdatum-Feld
     */
    public const DEFAULT_CREATED_FIELD = 'Erstellt';

    /**
     * Einzigartige ID der Konfiguration
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Name der CSV-Spalte für die Ticket-ID
     * Standardwert: 'Vorgangsschlüssel'
     */
    #[ORM\Column(length: 50)]
    private ?string $ticketIdField = self::DEFAULT_TICKET_ID_FIELD;

    /**
     * Name der CSV-Spalte für den Benutzernamen
     * Standardwert: 'Autor'
     */
    #[ORM\Column(length: 50)]
    private ?string $usernameField = self::DEFAULT_USERNAME_FIELD;

    /**
     * Name der CSV-Spalte für den Ticket-Namen
     * Standardwert: 'Zusammenfassung'
     */
    #[ORM\Column(length: 50)]
    private ?string $ticketNameField = self::DEFAULT_TICKET_NAME_FIELD;

    /**
     * Name der CSV-Spalte für das Erstellungsdatum
     * Standardwert: 'Erstellt'
     */
    #[ORM\Column(length: 50)]
    private ?string $createdField = self::DEFAULT_CREATED_FIELD;

    /**
     * Gibt die ID der Konfiguration zurück
     *
     * @return int|null Die ID oder null, wenn nicht gesetzt
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gibt den konfigurierten Feldnamen für die Ticket-ID zurück
     *
     * @return string Der Feldname für die Ticket-ID (nie null dank Fallback)
     */
    public function getTicketIdField(): ?string
    {
        return $this->ticketIdField ?: self::DEFAULT_TICKET_ID_FIELD;
    }

    /**
     * Setzt den Feldnamen für die Ticket-ID
     *
     * @param string|null $ticketIdField Der neue Feldname (wird auf Standardwert gesetzt wenn null/leer)
     * @return static Diese Instanz für Method Chaining
     */
    public function setTicketIdField(?string $ticketIdField): static
    {
        $this->ticketIdField = $ticketIdField ?: self::DEFAULT_TICKET_ID_FIELD;

        return $this;
    }

    /**
     * Gibt den konfigurierten Feldnamen für den Benutzernamen zurück
     *
     * @return string Der Feldname für den Benutzernamen (nie null dank Fallback)
     */
    public function getUsernameField(): ?string
    {
        return $this->usernameField ?: self::DEFAULT_USERNAME_FIELD;
    }

    /**
     * Setzt den Feldnamen für den Benutzernamen
     *
     * @param string|null $usernameField Der neue Feldname (wird auf Standardwert gesetzt wenn null/leer)
     * @return static Diese Instanz für Method Chaining
     */
    public function setUsernameField(?string $usernameField): static
    {
        $this->usernameField = $usernameField ?: self::DEFAULT_USERNAME_FIELD;

        return $this;
    }

    /**
     * Gibt den konfigurierten Feldnamen für den Ticket-Namen zurück
     *
     * @return string Der Feldname für den Ticket-Namen (nie null dank Fallback)
     */
    public function getTicketNameField(): ?string
    {
        return $this->ticketNameField ?: self::DEFAULT_TICKET_NAME_FIELD;
    }

    /**
     * Setzt den Feldnamen für den Ticket-Namen
     *
     * @param string|null $ticketNameField Der neue Feldname (wird auf Standardwert gesetzt wenn null/leer)
     * @return static Diese Instanz für Method Chaining
     */
    public function setTicketNameField(?string $ticketNameField): static
    {
        $this->ticketNameField = $ticketNameField ?: self::DEFAULT_TICKET_NAME_FIELD;

        return $this;
    }

    /**
     * Gibt den konfigurierten Feldnamen für das Erstellungsdatum zurück
     *
     * @return string Der Feldname für das Erstellungsdatum (nie null dank Fallback)
     */
    public function getCreatedField(): ?string
    {
        return $this->createdField ?: self::DEFAULT_CREATED_FIELD;
    }

    /**
     * Setzt den Feldnamen für das Erstellungsdatum
     *
     * @param string|null $createdField Der neue Feldname (wird auf Standardwert gesetzt wenn null/leer)
     * @return static Diese Instanz für Method Chaining
     */
    public function setCreatedField(?string $createdField): static
    {
        $this->createdField = $createdField ?: self::DEFAULT_CREATED_FIELD;

        return $this;
    }

    /**
     * Gibt die konfigurierten Feldnamen als assoziatives Array zurück
     *
     * Diese Methode ist praktisch für die Verarbeitung von CSV-Dateien,
     * da sie alle Feld-Zuordnungen in einem strukturierten Format bereitstellt.
     *
     * @return array<string, string> Array mit den Zuordnungen: ['ticketId' => 'Feldname', ...]
     */
    public function getFieldMapping(): array
    {
        return [
            'ticketId' => $this->getTicketIdField(),
            'username' => $this->getUsernameField(),
            'ticketName' => $this->getTicketNameField(),
            'created' => $this->getCreatedField(),
        ];
    }
}
