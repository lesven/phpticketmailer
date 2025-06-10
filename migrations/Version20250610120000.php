<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration für die Ergänzung der SMTPConfig-Tabelle um SSL-Verifizierungs-Option
 */
final class Version20250610120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fügt die verifySSL-Option zur SMTPConfig-Tabelle hinzu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smtp_config ADD verify_ssl TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smtp_config DROP COLUMN verify_ssl');
    }
}
