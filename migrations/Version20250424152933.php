<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Manual migration for AdminPassword entity (User Story 13)
 */
final class Version20250424152933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Erstellt die admin_password Tabelle für die Passwort-Authentifizierung (User Story 13)';
    }

    public function up(Schema $schema): void
    {
        // Diese Migration ist eine dokumentierte Version der bereits vorhandenen Tabelle
        $this->addSql('CREATE TABLE IF NOT EXISTS admin_password (
            id INT AUTO_INCREMENT NOT NULL,
            password VARCHAR(255) NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Standardpasswort "geheim" einfügen, falls noch kein Eintrag existiert
        $this->addSql('INSERT INTO admin_password (password) 
            SELECT * FROM (SELECT "' . password_hash('geheim', PASSWORD_BCRYPT) . '") AS tmp 
            WHERE NOT EXISTS (SELECT 1 FROM admin_password) LIMIT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS admin_password');
    }
}
