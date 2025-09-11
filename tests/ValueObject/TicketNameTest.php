<?php

namespace App\Tests\ValueObject;

use App\ValueObject\TicketName;
use App\Exception\InvalidTicketNameException;
use PHPUnit\Framework\TestCase;

class TicketNameTest extends TestCase
{
    public function testCreateFromValidString(): void
    {
        $ticketName = TicketName::fromString('Test Ticket');
        
        $this->assertEquals('Test Ticket', $ticketName->getValue());
        $this->assertEquals('Test Ticket', (string) $ticketName);
    }

    public function testTrimsWhitespace(): void
    {
        $ticketName = TicketName::fromString('  Trimmed Ticket  ');
        
        $this->assertEquals('Trimmed Ticket', $ticketName->getValue());
    }

    public function testEquality(): void
    {
        $ticketName1 = TicketName::fromString('Same Ticket');
        $ticketName2 = TicketName::fromString('Same Ticket');
        $ticketName3 = TicketName::fromString('Different Ticket');
        
        $this->assertTrue($ticketName1->equals($ticketName2));
        $this->assertFalse($ticketName1->equals($ticketName3));
    }

    public function testMaxLengthTruncation(): void
    {
        $longName = str_repeat('A', 60); // 60 characters
        $ticketName = TicketName::fromString($longName);
        
        $this->assertEquals(50, mb_strlen($ticketName->getValue()));
        $this->assertEquals(str_repeat('A', 50), $ticketName->getValue());
    }

    public function testEmptyStringThrowsException(): void
    {
        $this->expectException(InvalidTicketNameException::class);
        $this->expectExceptionMessage('Ticket name cannot be empty');
        
        TicketName::fromString('');
    }

    public function testWhitespaceOnlyStringThrowsException(): void
    {
        $this->expectException(InvalidTicketNameException::class);
        $this->expectExceptionMessage('Ticket name cannot be empty');
        
        TicketName::fromString('   ');
    }

    public function testDirectConstructorWithEmptyStringThrowsException(): void
    {
        $this->expectException(InvalidTicketNameException::class);
        $this->expectExceptionMessage('Ticket name cannot be empty');
        
        new TicketName('');
    }

    public function testDirectConstructorWithTooLongStringThrowsException(): void
    {
        $this->expectException(InvalidTicketNameException::class);
        $this->expectExceptionMessage('Ticket name exceeds maximum length of 50 characters');
        
        new TicketName(str_repeat('A', 51));
    }

    /**
     * @dataProvider validTicketNameProvider
     */
    public function testValidTicketNames(string $name, string $expected): void
    {
        $ticketName = TicketName::fromString($name);
        $this->assertEquals($expected, $ticketName->getValue());
    }

    public static function validTicketNameProvider(): array
    {
        return [
            'simple name' => ['Bug Report', 'Bug Report'],
            'with numbers' => ['Issue #123', 'Issue #123'],
            'with special chars' => ['Feature: New User Interface', 'Feature: New User Interface'],
            'unicode characters' => ['Umlaute: äöü ÄÖÜ ß', 'Umlaute: äöü ÄÖÜ ß'],
            'exactly 50 chars' => [str_repeat('X', 50), str_repeat('X', 50)],
        ];
    }
}
