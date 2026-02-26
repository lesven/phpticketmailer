<?php

namespace App\Tests\Service;

use App\Dto\CsvProcessingResult;
use App\Service\SessionManager;
use App\ValueObject\UnknownUserWithTicket;
use App\ValueObject\Username;
use App\ValueObject\TicketId;
use App\ValueObject\TicketName;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests für UnknownUserWithTicket Serialisierung/Deserialisierung im SessionManager
 * 
 * Diese Tests überprüfen, dass UnknownUserWithTicket-Objekte korrekt in der Session
 * gespeichert und wieder rekonstruiert werden können.
 */
class SessionManagerUnknownUserWithTicketTest extends TestCase
{
    private SessionManager $sessionManager;
    private RequestStack $requestStack;
    private SessionInterface $session;
    private array $sessionStore = [];

    protected function setUp(): void
    {
        $this->sessionStore = [];
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->session = $this->createMock(SessionInterface::class);
        
        // Session-Mock mit echtem Storage
        $this->session->method('set')->willReturnCallback(function($key, $value) {
            $this->sessionStore[$key] = $value;
        });
        $this->session->method('get')->willReturnCallback(function($key, $default = null) {
            return $this->sessionStore[$key] ?? $default;
        });
        $this->session->method('remove')->willReturnCallback(function($key) {
            unset($this->sessionStore[$key]);
        });
        
        $this->requestStack->method('getSession')->willReturn($this->session);
        $this->sessionManager = new SessionManager($this->requestStack);
    }

    /**
     * Testet das Speichern und Abrufen von UnknownUserWithTicket-Objekten
     */
    public function testStoreAndRetrieveUnknownUserWithTicketObjects(): void
    {
        $unknownUser1 = new UnknownUserWithTicket(
            new Username('testuser1'),
            new TicketId('T-001'),
            new TicketName('Test Issue 1')
        );
        
        $unknownUser2 = new UnknownUserWithTicket(
            new Username('testuser2'),
            new TicketId('T-002'),
            new TicketName('Test Issue 2')
        );

        $processingResult = new CsvProcessingResult(
            unknownUsers: [$unknownUser1, $unknownUser2]
        );

        // Speichern
        $this->sessionManager->storeUploadResults($processingResult);

        // Abrufen
        $retrievedUsers = $this->sessionManager->getUnknownUsers();

        $this->assertCount(2, $retrievedUsers);
        
        // Prüfung erstes Objekt
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[0]);
        $this->assertEquals('testuser1', $retrievedUsers[0]->getUsernameString());
        $this->assertEquals('T-001', $retrievedUsers[0]->getTicketIdString());
        $this->assertEquals('Test Issue 1', $retrievedUsers[0]->getTicketNameString());
        
        // Prüfung zweites Objekt
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[1]);
        $this->assertEquals('testuser2', $retrievedUsers[1]->getUsernameString());
        $this->assertEquals('T-002', $retrievedUsers[1]->getTicketIdString());
        $this->assertEquals('Test Issue 2', $retrievedUsers[1]->getTicketNameString());
    }

    /**
     * Testet UnknownUserWithTicket ohne TicketName (null)
     */
    public function testUnknownUserWithTicketWithoutTicketName(): void
    {
        $unknownUser = new UnknownUserWithTicket(
            new Username('testuser'),
            new TicketId('T-001'),
            null // Kein TicketName
        );

        $processingResult = new CsvProcessingResult(
            unknownUsers: [$unknownUser]
        );

        $this->sessionManager->storeUploadResults($processingResult);
        $retrievedUsers = $this->sessionManager->getUnknownUsers();

        $this->assertCount(1, $retrievedUsers);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[0]);
        $this->assertEquals('testuser', $retrievedUsers[0]->getUsernameString());
        $this->assertEquals('T-001', $retrievedUsers[0]->getTicketIdString());
        $this->assertNull($retrievedUsers[0]->getTicketNameString());
    }

    /**
     * Testet mehrere UnknownUserWithTicket-Objekte mit verschiedenen Ticket-IDs
     */
    public function testMultipleUnknownUserWithTicketObjects(): void
    {
        $unknownUser1 = new UnknownUserWithTicket(
            new Username('user1'),
            new TicketId('T-001'),
            new TicketName('Issue 1')
        );

        $unknownUser2 = new UnknownUserWithTicket(
            new Username('user2'),
            new TicketId('T-002'),
            new TicketName('Issue 2')
        );

        $unknownUser3 = new UnknownUserWithTicket(
            new Username('user3'),
            new TicketId('UNKNOWN'),
            null
        );

        $processingResult = new CsvProcessingResult(
            unknownUsers: [$unknownUser1, $unknownUser2, $unknownUser3]
        );

        $this->sessionManager->storeUploadResults($processingResult);
        $retrievedUsers = $this->sessionManager->getUnknownUsers();

        $this->assertCount(3, $retrievedUsers);
        
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[0]);
        $this->assertEquals('user1', $retrievedUsers[0]->getUsernameString());
        $this->assertEquals('T-001', $retrievedUsers[0]->getTicketIdString());
        
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[1]);
        $this->assertEquals('user2', $retrievedUsers[1]->getUsernameString());
        $this->assertEquals('T-002', $retrievedUsers[1]->getTicketIdString());
        
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[2]);
        $this->assertEquals('user3', $retrievedUsers[2]->getUsernameString());
        $this->assertEquals('UNKNOWN', $retrievedUsers[2]->getTicketIdString());
    }

    /**
     * Testet Edge Case: Leere unknownUsers
     */
    public function testEmptyUnknownUsers(): void
    {
        $processingResult = new CsvProcessingResult(
            unknownUsers: []
        );

        $this->sessionManager->storeUploadResults($processingResult);
        $retrievedUsers = $this->sessionManager->getUnknownUsers();

        $this->assertEmpty($retrievedUsers);
    }

    /**
     * Testet Session-Clear beim Speichern
     */
    public function testSessionClearOnStore(): void
    {
        // Setze vorherige Session-Daten
        $this->sessionStore['unknown_users'] = ['old_user'];
        $this->sessionStore['valid_tickets'] = ['old_ticket'];

        $unknownUser = new UnknownUserWithTicket(
            new Username('newuser'),
            new TicketId('T-NEW'),
            new TicketName('New Issue')
        );

        $processingResult = new CsvProcessingResult(
            unknownUsers: [$unknownUser]
        );

        $this->sessionManager->storeUploadResults($processingResult);
        $retrievedUsers = $this->sessionManager->getUnknownUsers();

        // Alte Daten sollten überschrieben sein
        $this->assertCount(1, $retrievedUsers);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[0]);
        $this->assertEquals('newuser', $retrievedUsers[0]->getUsernameString());
    }

    /**
     * Testet Sonderzeichen in Benutzernamen und Ticket-Namen
     */
    public function testSpecialCharactersInUserAndTicketNames(): void
    {
        $unknownUser = new UnknownUserWithTicket(
            new Username('user.with-special_chars@domain'),
            new TicketId('T-001'),
            new TicketName('Issue with "quotes" & special chars: äöü €')
        );

        $processingResult = new CsvProcessingResult(
            unknownUsers: [$unknownUser]
        );

        $this->sessionManager->storeUploadResults($processingResult);
        $retrievedUsers = $this->sessionManager->getUnknownUsers();

        $this->assertCount(1, $retrievedUsers);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[0]);
        $this->assertEquals('user.with-special_chars@domain', $retrievedUsers[0]->getUsernameString());
        $this->assertEquals('T-001', $retrievedUsers[0]->getTicketIdString());
        $this->assertEquals('Issue with "quotes" & special chars: äöü €', $retrievedUsers[0]->getTicketNameString());
    }

    /**
     * Testet normale Länge Ticket-Namen (unter 50 Zeichen)
     */
    public function testNormalLengthTicketName(): void
    {
        $normalTicketName = 'Normal length ticket name within limits';
        
        $unknownUser = new UnknownUserWithTicket(
            new Username('testuser'),
            new TicketId('T-001'),
            new TicketName($normalTicketName)
        );

        $processingResult = new CsvProcessingResult(
            unknownUsers: [$unknownUser]
        );

        $this->sessionManager->storeUploadResults($processingResult);
        $retrievedUsers = $this->sessionManager->getUnknownUsers();

        $this->assertCount(1, $retrievedUsers);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[0]);
        $this->assertEquals($normalTicketName, $retrievedUsers[0]->getTicketNameString());
    }

    /**
     * Testet robuste Fehlerbehandlung bei ungültigen Session-Daten
     */
    public function testRobustErrorHandling(): void
    {
        // Simuliere korrupte Session-Daten - mit gültigen Teilen
        $this->sessionStore['unknown_users'] = [
            ['type' => 'UnknownUserWithTicket', 'username' => 'valid_user', 'ticketId' => 'T-001', 'ticketName' => 'Valid'],
            ['type' => 'UnknownUserWithTicket', 'username' => '', 'ticketId' => 'T-002'], // empty username = skipped
            ['invalid' => 'data'], // missing type key = skipped
        ];

        $retrievedUsers = $this->sessionManager->getUnknownUsers();

        // Sollte graceful mit korrupten Daten umgehen
        $this->assertCount(1, $retrievedUsers);
        
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[0]);
        $this->assertEquals('valid_user', $retrievedUsers[0]->getUsernameString());
    }
}