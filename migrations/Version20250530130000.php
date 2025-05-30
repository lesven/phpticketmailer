<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration für Ticket-Basis-URL in SMTP-Konfiguration
 */
final class Version20250530130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fügt ticket_base_url Feld zur smtpconfig Tabelle hinzu';
    }

    public function up(Schema $schema): void
    {
        // Ticket-Basis-URL Feld zur SMTPConfig Tabelle hinzufügen
        $this->addSql('ALTER TABLE smtpconfig ADD ticket_base_url VARCHAR(255) NOT NULL DEFAULT \'https://www.ticket.de\'');
    }

    public function down(Schema $schema): void
    {
        // Ticket-Basis-URL Feld aus der SMTPConfig Tabelle entfernen
        $this->addSql('ALTER TABLE smtpconfig DROP COLUMN ticket_base_url');
    }
}
