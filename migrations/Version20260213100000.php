<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create email_templates table for versioned email templates with valid_from date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_templates (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            valid_from DATE NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            INDEX idx_email_templates_valid_from (valid_from)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_templates');
    }
}
