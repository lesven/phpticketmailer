<?php

use PHPUnit\Framework\TestCase;
use App\Entity\CsvFieldConfig;

final class CsvFieldConfigTest extends TestCase
{
    public function testDefaultFieldNamesAndMapping(): void
    {
        $config = new CsvFieldConfig();

        $this->assertSame('ticketId', $config->getTicketIdField());
        $this->assertSame('username', $config->getUsernameField());
        $this->assertSame('ticketName', $config->getTicketNameField());

        $mapping = $config->getFieldMapping();
        $this->assertIsArray($mapping);
        $this->assertSame('ticketId', $mapping['ticketId']);
        $this->assertSame('username', $mapping['username']);
        $this->assertSame('ticketName', $mapping['ticketName']);
    }

    public function testSettersAcceptNullAndFallbackToDefaults(): void
    {
        $config = new CsvFieldConfig();

        $config->setTicketIdField(null);
        $this->assertSame('ticketId', $config->getTicketIdField());

        $config->setUsernameField('');
        $this->assertSame('username', $config->getUsernameField());

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
            'all null' => [null, null, null, ['ticketId' => 'ticketId', 'username' => 'username', 'ticketName' => 'ticketName']],
            'empty strings' => ['', '', '', ['ticketId' => 'ticketId', 'username' => 'username', 'ticketName' => 'ticketName']],
            'custom mix' => ['id', null, '', ['ticketId' => 'id', 'username' => 'username', 'ticketName' => 'ticketName']],
        ];
    }
}
