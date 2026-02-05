<?php

use PHPUnit\Framework\TestCase;
use App\Entity\CsvFieldConfig;

final class CsvFieldConfigTest extends TestCase
{
    public function testDefaultFieldNamesAndMapping(): void
    {
        $config = new CsvFieldConfig();

        $this->assertSame('Vorgangsschlüssel', $config->getTicketIdField());
        $this->assertSame('Autor', $config->getUsernameField());
        $this->assertSame('Zusammenfassung', $config->getTicketNameField());
        $this->assertSame('Erstellt', $config->getCreatedField());

        $mapping = $config->getFieldMapping();
        $this->assertIsArray($mapping);
        $this->assertSame('Vorgangsschlüssel', $mapping['ticketId']);
        $this->assertSame('Autor', $mapping['username']);
        $this->assertSame('Zusammenfassung', $mapping['ticketName']);
        $this->assertSame('Erstellt', $mapping['created']);
    }

    public function testSettersAcceptNullAndFallbackToDefaults(): void
    {
        $config = new CsvFieldConfig();

        $config->setTicketIdField(null);
        $this->assertSame('Vorgangsschlüssel', $config->getTicketIdField());

        $config->setUsernameField('');
        $this->assertSame('Autor', $config->getUsernameField());

        $config->setTicketNameField('myTicket');
        $this->assertSame('myTicket', $config->getTicketNameField());

        $config->setCreatedField('2026-01-01');
        $this->assertSame('2026-01-01', $config->getCreatedField());
    }

    /**
     * @dataProvider invalidFieldProvider
     */
    public function testInvalidFieldValuesFallback(?string $ticketId, ?string $username, ?string $ticketName, ?string $created, array $expected): void
    {
        $c = new CsvFieldConfig();
        $c->setTicketIdField($ticketId);
        $c->setUsernameField($username);
        $c->setTicketNameField($ticketName);
        $c->setCreatedField($created);

        $this->assertSame($expected['ticketId'], $c->getTicketIdField());
        $this->assertSame($expected['username'], $c->getUsernameField());
        $this->assertSame($expected['ticketName'], $c->getTicketNameField());
        $this->assertSame($expected['created'], $c->getCreatedField());
    }

    public static function invalidFieldProvider(): array
    {
        return [
            'all null' => [null, null, null, null, ['ticketId' => 'Vorgangsschlüssel', 'username' => 'Autor', 'ticketName' => 'Zusammenfassung', 'created' => 'Erstellt']],
            'empty strings' => ['', '', '', '', ['ticketId' => 'Vorgangsschlüssel', 'username' => 'Autor', 'ticketName' => 'Zusammenfassung', 'created' => 'Erstellt']],
            'custom mix' => ['id', null, '', 'Datum', ['ticketId' => 'id', 'username' => 'Autor', 'ticketName' => 'Zusammenfassung', 'created' => 'Datum']],
        ];
    }
}
