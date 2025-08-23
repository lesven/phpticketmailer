<?php

namespace App\Tests\ValueObject;

use App\ValueObject\TicketNumber;
use App\Exception\InvalidTicketNumberException;
use PHPUnit\Framework\TestCase;

class TicketNumberTest extends TestCase
{
    public function testValidTicketNumber(): void
    {
        $ticketNumber = TicketNumber::fromString('TICKET-123456');
        
        $this->assertEquals('TICKET-123456', $ticketNumber->getValue());
        $this->assertEquals(123456, $ticketNumber->getNumber());
        $this->assertEquals('TICKET-123456', (string) $ticketNumber);
    }

    public function testGenerateTicketNumber(): void
    {
        $ticketNumber = TicketNumber::generate(42);
        
        $this->assertEquals('TICKET-000042', $ticketNumber->getValue());
        $this->assertEquals(42, $ticketNumber->getNumber());
    }

    public function testTicketNumberEquality(): void
    {
        $ticket1 = TicketNumber::fromString('TICKET-123456');
        $ticket2 = TicketNumber::fromString('TICKET-123456');
        $ticket3 = TicketNumber::fromString('TICKET-654321');
        
        $this->assertTrue($ticket1->equals($ticket2));
        $this->assertFalse($ticket1->equals($ticket3));
    }

    /**
     * @dataProvider invalidTicketNumberProvider
     */
    public function testInvalidTicketNumber(string $invalidTicket): void
    {
        $this->expectException(InvalidTicketNumberException::class);
        TicketNumber::fromString($invalidTicket);
    }

    public function invalidTicketNumberProvider(): array
    {
        return [
            [''],
            ['TICKET'],
            ['TICKET-'],
            ['TICKET-ABC'],
            ['123456'],
            ['ticket-123456'],
            ['TICKET-0'],
            ['TICKET-1000000'], // Too high
        ];
    }

    public function testGenerateWithTooLowNumber(): void
    {
        $this->expectException(InvalidTicketNumberException::class);
        TicketNumber::generate(0);
    }

    public function testGenerateWithTooHighNumber(): void
    {
        $this->expectException(InvalidTicketNumberException::class);
        TicketNumber::generate(1000000);
    }
}