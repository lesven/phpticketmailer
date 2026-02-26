<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ticket_created column to emails_sent table to store ticket creation date from CSV';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE emails_sent ADD ticket_created VARCHAR(50) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE emails_sent DROP ticket_created');
    }
}
