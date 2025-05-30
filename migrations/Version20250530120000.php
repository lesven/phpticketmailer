<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration für User Story 17: CSV-Feldkonfiguration
 */
final class Version20250530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Erstellt die Tabelle csv_field_config für konfigurierbare CSV-Spalten';
    }

    public function up(Schema $schema): void
    {
        // Tabelle für CSV-Feldkonfiguration erstellen
        $this->addSql('CREATE TABLE csv_field_config (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            ticket_id_field VARCHAR(50) NOT NULL DEFAULT "ticketId", 
            username_field VARCHAR(50) NOT NULL DEFAULT "username", 
            ticket_name_field VARCHAR(50) NOT NULL DEFAULT "ticketName"
        )');
        
        // Standardkonfiguration einfügen
        $this->addSql('INSERT INTO csv_field_config (ticket_id_field, username_field, ticket_name_field) VALUES ("ticketId", "username", "ticketName")');
    }

    public function down(Schema $schema): void
    {
        // Tabelle löschen
        $this->addSql('DROP TABLE csv_field_config');
    }
}
