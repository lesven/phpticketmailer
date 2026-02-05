<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_field to csv_field_config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE csv_field_config ADD created_field VARCHAR(50) NOT NULL DEFAULT 'Erstellt'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE csv_field_config DROP created_field');
    }
}
