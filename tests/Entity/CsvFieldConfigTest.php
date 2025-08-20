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
}
