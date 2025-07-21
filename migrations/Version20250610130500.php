<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250610130500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fügt excluded_from_surveys Feld zur users Tabelle hinzu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD excluded_from_surveys TINYINT(1) NOT NULL DEFAULT 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users DROP COLUMN excluded_from_surveys");
    }
}
