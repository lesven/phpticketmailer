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
    private ?string $ticketIdField = 'ticketId';

    #[ORM\Column(length: 50)]
    private ?string $usernameField = 'username';

    #[ORM\Column(length: 50)]
    private ?string $ticketNameField = 'ticketName';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicketIdField(): ?string
    {
        return $this->ticketIdField ?: 'ticketId';
    }

    public function setTicketIdField(?string $ticketIdField): static
    {
        $this->ticketIdField = $ticketIdField ?: 'ticketId';

        return $this;
    }

    public function getUsernameField(): ?string
    {
        return $this->usernameField ?: 'username';
    }

    public function setUsernameField(?string $usernameField): static
    {
        $this->usernameField = $usernameField ?: 'username';

        return $this;
    }

    public function getTicketNameField(): ?string
    {
        return $this->ticketNameField ?: 'ticketName';
    }

    public function setTicketNameField(?string $ticketNameField): static
    {
        $this->ticketNameField = $ticketNameField ?: 'ticketName';

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
