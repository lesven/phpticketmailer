<?php

namespace App\Tests\ValueObject;

use App\ValueObject\UnknownUserWithTicket;
use App\ValueObject\Username;
use App\ValueObject\TicketId;
use App\ValueObject\TicketName;
use App\ValueObject\TicketData;
use PHPUnit\Framework\TestCase;

class UnknownUserWithTicketTest extends TestCase
{
    public function testCreationFromConstructor(): void
    {
        $username = Username::fromString('testuser');
        $ticketId = TicketId::fromString('TICKET-123');
        $ticketName = TicketName::fromString('Test Ticket');

        $unknownUser = new UnknownUserWithTicket($username, $ticketId, $ticketName);

        $this->assertEquals('testuser', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-123', $unknownUser->getTicketIdString());
        $this->assertEquals('Test Ticket', $unknownUser->getTicketNameString());
    }

    public function testCreationFromTicketData(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-456', 'anotheruser', 'Another Test Ticket');

        $unknownUser = UnknownUserWithTicket::fromTicketData($ticketData);

        $this->assertEquals('anotheruser', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-456', $unknownUser->getTicketIdString());
        $this->assertEquals('Another Test Ticket', $unknownUser->getTicketNameString());
    }

    public function testWithoutTicketName(): void
    {
        $username = Username::fromString('testuser');
        $ticketId = TicketId::fromString('TICKET-789');

        $unknownUser = new UnknownUserWithTicket($username, $ticketId);

        $this->assertEquals('testuser', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-789', $unknownUser->getTicketIdString());
        $this->assertNull($unknownUser->getTicketNameString());
    }

    public function testFromTicketDataWithoutTicketName(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-999', 'usernoticket', '');

        $unknownUser = UnknownUserWithTicket::fromTicketData($ticketData);

        $this->assertEquals('usernoticket', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-999', $unknownUser->getTicketIdString());
        $this->assertNull($unknownUser->getTicketNameString());
    }
}