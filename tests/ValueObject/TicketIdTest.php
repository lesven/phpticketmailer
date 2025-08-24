<?php

namespace App\Tests\ValueObject;

use App\ValueObject\TicketId;
use App\Exception\InvalidTicketIdException;
use PHPUnit\Framework\TestCase;

class TicketIdTest extends TestCase
{
    public function testCreateFromValidString(): void
    {
        $ticketId = TicketId::fromString('TICKET-123');
        
        $this->assertEquals('TICKET-123', $ticketId->getValue());
        $this->assertEquals('TICKET-123', (string) $ticketId);
    }

    public function testCreateFromStringTrimsWhitespace(): void
    {
        $ticketId = TicketId::fromString('  ABC-456  ');
        
        $this->assertEquals('ABC-456', $ticketId->getValue());
    }

    public function testEquality(): void
    {
        $ticketId1 = TicketId::fromString('TEST-001');
        $ticketId2 = TicketId::fromString('TEST-001');
        $ticketId3 = TicketId::fromString('TEST-002');
        
        $this->assertTrue($ticketId1->equals($ticketId2));
        $this->assertFalse($ticketId1->equals($ticketId3));
    }

    public function testHasPrefix(): void
    {
        $ticketId = TicketId::fromString('SUPPORT-123');
        
        $this->assertTrue($ticketId->hasPrefix('SUPPORT'));
        $this->assertTrue($ticketId->hasPrefix('support')); // Case insensitive
        $this->assertFalse($ticketId->hasPrefix('SALES'));
    }

    public function testGetPrefix(): void
    {
        $ticketId1 = TicketId::fromString('SUPPORT-123');
        $ticketId2 = TicketId::fromString('SALES_456');
        $ticketId3 = TicketId::fromString('BUG.789');
        $ticketId4 = TicketId::fromString('SIMPLE');
        
        $this->assertEquals('SUPPORT', $ticketId1->getPrefix());
        $this->assertEquals('SALES', $ticketId2->getPrefix());
        $this->assertEquals('BUG', $ticketId3->getPrefix());
        $this->assertEquals('SIMPLE', $ticketId4->getPrefix());
    }

    /**
     * @dataProvider validTicketIdProvider
     */
    public function testValidTicketIds(string $ticketId): void
    {
        $ticket = TicketId::fromString($ticketId);
        $this->assertInstanceOf(TicketId::class, $ticket);
    }

    public function validTicketIdProvider(): array
    {
        return [
            ['ABC'],
            ['123'],
            ['TICKET-123'],
            ['SUPPORT_456'],
            ['BUG.789'],
            ['ABC-123-DEF'],
            ['TEST_001_FINAL'],
            ['ISSUE.BUG.001'],
            ['a1b2c3'],
            ['X' . str_repeat('Y', 48)], // 49 characters (max 50)
        ];
    }

    /**
     * @dataProvider invalidTicketIdProvider
     */
    public function testInvalidTicketIds(string $ticketId, ?string $expectedMessage = null): void
    {
        $this->expectException(InvalidTicketIdException::class);
        if ($expectedMessage) {
            $this->expectExceptionMessage($expectedMessage);
        }
        TicketId::fromString($ticketId);
    }

    public function invalidTicketIdProvider(): array
    {
        return [
            ['', 'cannot be empty'],
            ['AB', 'at least 3 characters'], // Too short
            [str_repeat('X', 51), 'must not exceed 50 characters'], // Too long
            ['TICKET--123', 'consecutive separators'], // Double separator
            ['-TICKET-123', 'cannot start or end with separators'], // Starts with separator
            ['TICKET-123-', 'cannot start or end with separators'], // Ends with separator
            ['TICKET 123', 'invalid characters'], // Contains space
            ['TICKET@123', 'invalid characters'], // Contains @
            ['TICKET#123', 'invalid characters'], // Contains #
            ['TICKET(123)', 'invalid characters'], // Contains parentheses
        ];
    }

    public function testEmptyTicketIdThrowsException(): void
    {
        $this->expectException(InvalidTicketIdException::class);
        $this->expectExceptionMessage('Ticket ID cannot be empty');
        TicketId::fromString('');
    }
}