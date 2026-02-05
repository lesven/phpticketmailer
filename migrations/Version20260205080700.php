<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add created_field column to csv_field_config table
 * This allows configuring the CSV column name for ticket creation date
 */
final class Version20260205080700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_field column to csv_field_config table with default value "Erstellt"';
    }

    public function up(Schema $schema): void
    {
        // Add created_field column with default value 'Erstellt'
        $this->addSql("ALTER TABLE csv_field_config ADD created_field VARCHAR(50) NOT NULL DEFAULT 'Erstellt'");
    }

    public function down(Schema $schema): void
    {
        // Remove created_field column
        $this->addSql('ALTER TABLE csv_field_config DROP created_field');
    }
}
