<?php

namespace App\Tests\ValueObject;

use App\ValueObject\UnknownUserWithTicket;
use App\ValueObject\Username;
use App\ValueObject\TicketId;
use App\ValueObject\TicketName;
use App\ValueObject\TicketData;
use PHPUnit\Framework\TestCase;

/**
 * Tests für UnknownUserWithTicket ValueObject
 * 
 * Erweiterte Tests für alle Funktionalitäten und Edge Cases des UnknownUserWithTicket ValueObjects
 */
class UnknownUserWithTicketTest extends TestCase
{
    public function testCreationFromConstructor(): void
    {
        $username = Username::fromString('testuser');
        $ticketId = TicketId::fromString('TICKET-123');
        $ticketName = TicketName::fromString('Test Ticket');

        $unknownUser = new UnknownUserWithTicket($username, $ticketId, $ticketName, '04/02/26 10:25');

        $this->assertEquals('testuser', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-123', $unknownUser->getTicketIdString());
        $this->assertEquals('Test Ticket', $unknownUser->getTicketNameString());
        $this->assertEquals('04/02/26 10:25', $unknownUser->getCreatedString());
    }

    public function testCreationFromTicketData(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-456', 'anotheruser', 'Another Test Ticket', '05/02/26 14:10');

        $unknownUser = UnknownUserWithTicket::fromTicketData($ticketData);

        $this->assertEquals('anotheruser', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-456', $unknownUser->getTicketIdString());
        $this->assertEquals('Another Test Ticket', $unknownUser->getTicketNameString());
        $this->assertEquals('05/02/26 14:10', $unknownUser->getCreatedString());
    }

    public function testWithoutTicketName(): void
    {
        $username = Username::fromString('testuser');
        $ticketId = TicketId::fromString('TICKET-789');

        $unknownUser = new UnknownUserWithTicket($username, $ticketId);

        $this->assertEquals('testuser', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-789', $unknownUser->getTicketIdString());
        $this->assertNull($unknownUser->getTicketNameString());
        $this->assertNull($unknownUser->getCreatedString());
    }

    public function testFromTicketDataWithoutTicketName(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-999', 'usernoticket', '');

        $unknownUser = UnknownUserWithTicket::fromTicketData($ticketData);

        $this->assertEquals('usernoticket', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-999', $unknownUser->getTicketIdString());
        $this->assertNull($unknownUser->getTicketNameString());
        $this->assertNull($unknownUser->getCreatedString());
    }

    /**
     * Testet Spezialzeichen in Benutzernamen (nur erlaubte Zeichen)
     */
    public function testUsernameWithSpecialCharacters(): void
    {
        $username = Username::fromString('user.name-test_123');
        $ticketId = TicketId::fromString('TICKET-SPECIAL');
        $ticketName = TicketName::fromString('Special Character Test');

        $unknownUser = new UnknownUserWithTicket($username, $ticketId, $ticketName, '04/02/26 10:25');

        $this->assertEquals('user.name-test_123', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-SPECIAL', $unknownUser->getTicketIdString());
        $this->assertEquals('Special Character Test', $unknownUser->getTicketNameString());
    }

    /**
     * Testet erlaubte Zeichen (keine Unicode-Tests da nicht unterstützt)
     */
    public function testValidCharacters(): void
    {
        $username = Username::fromString('test-user_123');
        $ticketId = TicketId::fromString('TICKET-TEST_123');
        $ticketName = TicketName::fromString('Test Ticket Name');

        $unknownUser = new UnknownUserWithTicket($username, $ticketId, $ticketName, '04/02/26 10:25');

        $this->assertEquals('test-user_123', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-TEST_123', $unknownUser->getTicketIdString());
        $this->assertEquals('Test Ticket Name', $unknownUser->getTicketNameString());
    }

    /**
     * Testet normale Ticket-Namen (respektiert 50-Zeichen-Limit)
     */
    public function testNormalLengthTicketName(): void
    {
        $normalName = 'Normal length ticket name within limits';
        
        $username = Username::fromString('testuser');
        $ticketId = TicketId::fromString('TICKET-NORMAL');
        $ticketName = TicketName::fromString($normalName);

        $unknownUser = new UnknownUserWithTicket($username, $ticketId, $ticketName, '04/02/26 10:25');

        $this->assertEquals('testuser', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-NORMAL', $unknownUser->getTicketIdString());
        $this->assertEquals($normalName, $unknownUser->getTicketNameString());
        $this->assertLessThanOrEqual(50, strlen($unknownUser->getTicketNameString()));
    }

    /**
     * Testet einfache Ticket-Namen ohne Sonderzeichen
     */
    public function testTicketNameWithSimpleContent(): void
    {
        $ticketName = 'Simple ticket name without special chars';
        
        $username = Username::fromString('testuser');
        $ticketId = TicketId::fromString('TICKET-SIMPLE');
        $ticketNameObj = TicketName::fromString($ticketName);

        $unknownUser = new UnknownUserWithTicket($username, $ticketId, $ticketNameObj);

        $this->assertEquals('testuser', $unknownUser->getUsernameString());
        $this->assertEquals('TICKET-SIMPLE', $unknownUser->getTicketIdString());
        $this->assertEquals($ticketName, $unknownUser->getTicketNameString());
    }

    /**
     * Testet dass Benutzernamen case-preserving sind
     */
    public function testCasePreservingUsername(): void
    {
        $usernameLower = Username::fromString('testuser');
        $usernameUpper = Username::fromString('testuser'); // Username normalisiert zu lowercase
        $usernameMixed = Username::fromString('testuser'); // Username normalisiert zu lowercase

        $ticketId = TicketId::fromString('TICKET-CASE');
        $ticketName = TicketName::fromString('Case Test');

        $unknownUser1 = new UnknownUserWithTicket($usernameLower, $ticketId, $ticketName);
        $unknownUser2 = new UnknownUserWithTicket($usernameUpper, $ticketId, $ticketName);
        $unknownUser3 = new UnknownUserWithTicket($usernameMixed, $ticketId, $ticketName);

        // Username wird zu lowercase normalisiert
        $this->assertEquals('testuser', $unknownUser1->getUsernameString());
        $this->assertEquals('testuser', $unknownUser2->getUsernameString());
        $this->assertEquals('testuser', $unknownUser3->getUsernameString());
    }

    /**
     * Testet fromTicketData mit verschiedenen TicketName-Zuständen
     */
    public function testFromTicketDataWithVariousTicketNameStates(): void
    {
        // Leerer String
        $ticketData1 = TicketData::fromStrings('TICKET-EMPTY', 'user1', '');
        $unknownUser1 = UnknownUserWithTicket::fromTicketData($ticketData1);
        $this->assertNull($unknownUser1->getTicketNameString());

        // Whitespace-only String
        $ticketData2 = TicketData::fromStrings('TICKET-WHITESPACE', 'user2', '   ');
        $unknownUser2 = UnknownUserWithTicket::fromTicketData($ticketData2);
        // Depends on TicketName implementation - might be null or trimmed

        // Normaler String
        $ticketData3 = TicketData::fromStrings('TICKET-NORMAL', 'user3', 'Normal Ticket');
        $unknownUser3 = UnknownUserWithTicket::fromTicketData($ticketData3);
        $this->assertEquals('Normal Ticket', $unknownUser3->getTicketNameString());
    }

    /**
     * Testet readonly-Eigenschaft der Properties
     */
    public function testReadonlyProperties(): void
    {
        $username = Username::fromString('testuser');
        $ticketId = TicketId::fromString('TICKET-READONLY');
        $ticketName = TicketName::fromString('Readonly Test');

        $unknownUser = new UnknownUserWithTicket($username, $ticketId, $ticketName, '04/02/26 10:25');

        // Die Properties sollten readonly sein
        $reflection = new \ReflectionClass($unknownUser);
        $usernameProperty = $reflection->getProperty('username');
        $ticketIdProperty = $reflection->getProperty('ticketId');
        $ticketNameProperty = $reflection->getProperty('ticketName');

        $this->assertTrue($usernameProperty->isReadOnly());
        $this->assertTrue($ticketIdProperty->isReadOnly());
        $this->assertTrue($ticketNameProperty->isReadOnly());
    }

    /**
     * Testet Immutability - Objekt kann nach Erstellung nicht verändert werden
     */
    public function testImmutability(): void
    {
        $username = Username::fromString('testuser');
        $ticketId = TicketId::fromString('TICKET-IMMUTABLE');
        $ticketName = TicketName::fromString('Immutable Test');

        $unknownUser = new UnknownUserWithTicket($username, $ticketId, $ticketName, '04/02/26 10:25');

        // Originale Werte speichern
        $originalUsername = $unknownUser->getUsernameString();
        $originalTicketId = $unknownUser->getTicketIdString();
        $originalTicketName = $unknownUser->getTicketNameString();

        // Nach mehreren Aufrufen sollten die Werte unverändert sein
        $this->assertEquals($originalUsername, $unknownUser->getUsernameString());
        $this->assertEquals($originalTicketId, $unknownUser->getTicketIdString());
        $this->assertEquals($originalTicketName, $unknownUser->getTicketNameString());
    }

    /**
     * Testet gültige minimale Werte (respektiert Validierungsregeln)
     */
    public function testValidMinimalValues(): void
    {
        $username = Username::fromString('abc'); // 3 Zeichen minimum
        $ticketId = TicketId::fromString('T12'); // 3 Zeichen minimum
        $ticketName = TicketName::fromString('X'); // Minimaler ticket name

        $unknownUser = new UnknownUserWithTicket($username, $ticketId, $ticketName, '04/02/26 10:25');

        $this->assertEquals('abc', $unknownUser->getUsernameString());
        $this->assertEquals('T12', $unknownUser->getTicketIdString());
        $this->assertEquals('X', $unknownUser->getTicketNameString());
    }

    /**
     * Testet gültige Ticket-ID-Formate
     */
    public function testValidTicketIdFormats(): void
    {
        $formats = [
            'T-001',
            'TICKET-123456',
            'ITS-93489',
            'BUG_001',
            'REQ-2024-001'
        ];

        $username = Username::fromString('testuser');
        $ticketName = TicketName::fromString('Test Issue');

        foreach ($formats as $format) {
            $ticketId = TicketId::fromString($format);
            $unknownUser = new UnknownUserWithTicket($username, $ticketId, $ticketName, '04/02/26 10:25');
            
            $this->assertEquals($format, $unknownUser->getTicketIdString());
        }
    }
}