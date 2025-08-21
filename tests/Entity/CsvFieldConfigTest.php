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

        $mapping = $config->getFieldMapping();
        $this->assertIsArray($mapping);
        $this->assertSame('Vorgangsschlüssel', $mapping['ticketId']);
        $this->assertSame('Autor', $mapping['username']);
        $this->assertSame('Zusammenfassung', $mapping['ticketName']);
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
    }

    /**
     * @dataProvider invalidFieldProvider
     */
    public function testInvalidFieldValuesFallback(?string $ticketId, ?string $username, ?string $ticketName, array $expected): void
    {
        $c = new CsvFieldConfig();
        $c->setTicketIdField($ticketId);
        $c->setUsernameField($username);
        $c->setTicketNameField($ticketName);

        $this->assertSame($expected['ticketId'], $c->getTicketIdField());
        $this->assertSame($expected['username'], $c->getUsernameField());
        $this->assertSame($expected['ticketName'], $c->getTicketNameField());
    }

    public static function invalidFieldProvider(): array
    {
        return [
            'all null' => [null, null, null, ['ticketId' => 'Vorgangsschlüssel', 'username' => 'Autor', 'ticketName' => 'Zusammenfassung']],
            'empty strings' => ['', '', '', ['ticketId' => 'Vorgangsschlüssel', 'username' => 'Autor', 'ticketName' => 'Zusammenfassung']],
            'custom mix' => ['id', null, '', ['ticketId' => 'id', 'username' => 'Autor', 'ticketName' => 'Zusammenfassung']],
        ];
    }
}
