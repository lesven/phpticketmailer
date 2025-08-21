<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'csv_field_config')]
class CsvFieldConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $ticketIdField = 'Vorgangsschlüssel';

    #[ORM\Column(length: 50)]
    private ?string $usernameField = 'Autor';

    #[ORM\Column(length: 50)]
    private ?string $ticketNameField = 'Zusammenfassung';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicketIdField(): ?string
    {
        return $this->ticketIdField ?: 'Vorgangsschlüssel';
    }

    public function setTicketIdField(?string $ticketIdField): static
    {
        $this->ticketIdField = $ticketIdField ?: 'Vorgangsschlüssel';

        return $this;
    }

    public function getUsernameField(): ?string
    {
        return $this->usernameField ?: 'Autor';
    }

    public function setUsernameField(?string $usernameField): static
    {
        $this->usernameField = $usernameField ?: 'Autor';

        return $this;
    }

    public function getTicketNameField(): ?string
    {
        return $this->ticketNameField ?: 'Zusammenfassung';
    }

    public function setTicketNameField(?string $ticketNameField): static
    {
        $this->ticketNameField = $ticketNameField ?: 'Zusammenfassung';

        return $this;
    }

    /**
     * Gibt die konfigurierten Feldnamen als Array zurück
     */
    public function getFieldMapping(): array
    {
        return [
            'ticketId' => $this->getTicketIdField(),
            'username' => $this->getUsernameField(),
            'ticketName' => $this->getTicketNameField(),
        ];
    }
}
