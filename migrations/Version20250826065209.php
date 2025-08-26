<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250826065209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_password CHANGE password password VARCHAR(255) NOT NULL COMMENT \'(DC2Type:secure_password)\'');
        $this->addSql('ALTER TABLE csv_field_config CHANGE ticket_id_field ticket_id_field VARCHAR(50) NOT NULL, CHANGE username_field username_field VARCHAR(50) NOT NULL, CHANGE ticket_name_field ticket_name_field VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE emails_sent CHANGE ticket_id ticket_id VARCHAR(255) NOT NULL COMMENT \'(DC2Type:ticket_id)\', CHANGE username username VARCHAR(255) NOT NULL COMMENT \'(DC2Type:username)\', CHANGE email email VARCHAR(255) NOT NULL COMMENT \'(DC2Type:email_address)\', CHANGE status status VARCHAR(50) NOT NULL COMMENT \'(DC2Type:email_status)\'');
        $this->addSql('ALTER TABLE smtpconfig CHANGE username username VARCHAR(255) DEFAULT NULL COMMENT \'(DC2Type:username)\', CHANGE sender_email sender_email VARCHAR(255) NOT NULL COMMENT \'(DC2Type:email_address)\', CHANGE ticket_base_url ticket_base_url VARCHAR(255) NOT NULL, CHANGE verify_ssl verify_ssl TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE users CHANGE username username VARCHAR(255) NOT NULL COMMENT \'(DC2Type:username)\', CHANGE email email VARCHAR(255) NOT NULL COMMENT \'(DC2Type:email_address)\', CHANGE excluded_from_surveys excluded_from_surveys TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE emails_sent CHANGE ticket_id ticket_id VARCHAR(255) NOT NULL, CHANGE username username VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE smtpconfig CHANGE username username VARCHAR(255) DEFAULT NULL, CHANGE verify_ssl verify_ssl TINYINT(1) DEFAULT 1 NOT NULL, CHANGE sender_email sender_email VARCHAR(255) NOT NULL, CHANGE ticket_base_url ticket_base_url VARCHAR(255) DEFAULT \'https://www.ticket.de\' NOT NULL');
        $this->addSql('ALTER TABLE admin_password CHANGE password password VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE csv_field_config CHANGE ticket_id_field ticket_id_field VARCHAR(50) DEFAULT \'ticketId\' NOT NULL, CHANGE username_field username_field VARCHAR(50) DEFAULT \'username\' NOT NULL, CHANGE ticket_name_field ticket_name_field VARCHAR(50) DEFAULT \'ticketName\' NOT NULL');
        $this->addSql('ALTER TABLE users CHANGE username username VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE excluded_from_surveys excluded_from_surveys TINYINT(1) DEFAULT 0 NOT NULL');
    }
}
