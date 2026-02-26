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
use App\ValueObject\TicketData;

/**
 * Test-Klasse für den SessionManager
 * 
 * Diese Klasse testet die Funktionalität des SessionManagers,
 * der für das Speichern und Abrufen von Upload-Ergebnissen in der Session zuständig ist.
 */
class SessionManagerTest extends TestCase
{
    private SessionManager $sessionManager;
    private RequestStack $requestStack;
    private SessionInterface $session;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->session = $this->createMock(SessionInterface::class);
        
        $this->requestStack->method('getSession')
            ->willReturn($this->session);
        
        $this->sessionManager = new SessionManager($this->requestStack);
    }

    /**
     * Testet das Speichern vollständiger Upload-Ergebnisse in der Session
     */
    public function testStoreUploadResultsWithCompleteData(): void
    {
        $processingResult = new CsvProcessingResult(
            unknownUsers: [
                new UnknownUserWithTicket(new Username('user1'), new TicketId('T-001')),
                new UnknownUserWithTicket(new Username('user2'), new TicketId('T-002')),
                new UnknownUserWithTicket(new Username('user3'), new TicketId('T-003')),
            ],
            validTickets: [
                TicketData::fromStrings('T-001', 'john', 'Issue 1'),
                TicketData::fromStrings('T-002', 'jane', 'Issue 2')
            ]
        );

        $this->session->expects($this->exactly(2))
            ->method('set');

        $this->sessionManager->storeUploadResults($processingResult);
    }

    /**
     * Testet das Speichern von Ergebnissen ohne unbekannte Benutzer
     */
    public function testStoreUploadResultsWithNoUnknownUsers(): void
    {
        $processingResult = new CsvProcessingResult(
            validTickets: [TicketData::fromStrings('T-001', 'user1')]
        );

        $this->session->expects($this->exactly(2))
            ->method('set');

        $this->sessionManager->storeUploadResults($processingResult);
    }

    public function testStoreUploadResultsWithEmptyData(): void
    {
        $processingResult = new CsvProcessingResult();

        $this->session->expects($this->exactly(2))
            ->method('set');

        $this->sessionManager->storeUploadResults($processingResult);
    }

    public function testGetUnknownUsersReturnsDeserializedObjects(): void
    {
        $serializedData = [
            ['type' => 'UnknownUserWithTicket', 'username' => 'user1', 'ticketId' => 'T-001', 'ticketName' => 'Issue 1', 'created' => null],
            ['type' => 'UnknownUserWithTicket', 'username' => 'user2', 'ticketId' => 'T-002', 'ticketName' => null, 'created' => null],
        ];

        $this->session->expects($this->once())
            ->method('get')
            ->with('unknown_users', [])
            ->willReturn($serializedData);

        $result = $this->sessionManager->getUnknownUsers();

        $this->assertCount(2, $result);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $result[0]);
        $this->assertEquals('user1', $result[0]->getUsernameString());
        $this->assertEquals('T-001', $result[0]->getTicketIdString());
        $this->assertInstanceOf(UnknownUserWithTicket::class, $result[1]);
        $this->assertEquals('user2', $result[1]->getUsernameString());
    }

    public function testGetUnknownUsersReturnsEmptyArrayWhenNoData(): void
    {
        $this->session->expects($this->once())
            ->method('get')
            ->with('unknown_users', [])
            ->willReturn([]);

        $result = $this->sessionManager->getUnknownUsers();

        $this->assertEquals([], $result);
    }

    public function testGetValidTicketsReturnsStoredData(): void
    {
        $expectedTickets = [
            TicketData::fromStrings('T-001', 'john', 'Test Issue'),
            TicketData::fromStrings('T-002', 'jane', 'Another Issue')
        ];

        $this->session->expects($this->once())
            ->method('get')
            ->with('valid_tickets', [])
            ->willReturn($expectedTickets);

        $result = $this->sessionManager->getValidTickets();

        $this->assertEquals($expectedTickets, $result);
    }

    public function testGetValidTicketsReturnsEmptyArrayWhenNoData(): void
    {
        $this->session->expects($this->once())
            ->method('get')
            ->with('valid_tickets', [])
            ->willReturn([]);

        $result = $this->sessionManager->getValidTickets();

        $this->assertEquals([], $result);
    }

    public function testClearUploadDataRemovesBothKeys(): void
    {
        $this->session->expects($this->exactly(3))
            ->method('remove');

        $this->sessionManager->clearUploadData();
    }

    public function testConstructorAcceptsRequestStack(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $sessionManager = new SessionManager($requestStack);

        $this->assertInstanceOf(SessionManager::class, $sessionManager);
    }

    public function testGetUnknownUsersSkipsInvalidEntries(): void
    {
        $serializedData = [
            ['type' => 'UnknownUserWithTicket', 'username' => '', 'ticketId' => 'T-001'], // empty username
            ['type' => 'UnknownUserWithTicket', 'username' => 'valid_user', 'ticketId' => 'T-002'],
            ['invalid' => 'data'], // missing type key
        ];

        $this->session->method('get')
            ->with('unknown_users', [])
            ->willReturn($serializedData);

        $result = $this->sessionManager->getUnknownUsers();

        $this->assertCount(1, $result);
        $this->assertEquals('valid_user', $result[0]->getUsernameString());
    }

    public function testMultipleStoreOperationsOverwritePreviousData(): void
    {
        $firstResult = new CsvProcessingResult(
            unknownUsers: [
                new UnknownUserWithTicket(new Username('user1'), new TicketId('T-001')),
            ],
            validTickets: [TicketData::fromStrings('T-001', 'user1')]
        );

        $this->session->expects($this->exactly(4))
            ->method('set');

        $this->sessionManager->storeUploadResults($firstResult);

        $secondResult = new CsvProcessingResult(
            unknownUsers: [
                new UnknownUserWithTicket(new Username('user2'), new TicketId('T-002')),
                new UnknownUserWithTicket(new Username('user3'), new TicketId('T-003')),
            ],
            validTickets: [
                TicketData::fromStrings('T-002', 'user2'),
                TicketData::fromStrings('T-003', 'user3')
            ]
        );

        $this->sessionManager->storeUploadResults($secondResult);
    }

    public function testWorkflowIntegration(): void
    {
        $processingResult = new CsvProcessingResult(
            unknownUsers: [
                new UnknownUserWithTicket(new Username('unknown1'), new TicketId('T-001')),
                new UnknownUserWithTicket(new Username('unknown2'), new TicketId('T-002')),
            ],
            validTickets: [
                TicketData::fromStrings('T-100', 'known_user', 'Valid Ticket')
            ]
        );

        // 1. Store data
        $this->session->expects($this->exactly(2))
            ->method('set');

        $this->sessionManager->storeUploadResults($processingResult);

        // 2. Retrieve data (simulate serialized session data)
        $this->session->method('get')
            ->willReturnCallback(function ($key, $default) {
                if ($key === 'unknown_users') {
                    return [
                        ['type' => 'UnknownUserWithTicket', 'username' => 'unknown1', 'ticketId' => 'T-001', 'ticketName' => null, 'created' => null],
                        ['type' => 'UnknownUserWithTicket', 'username' => 'unknown2', 'ticketId' => 'T-002', 'ticketName' => null, 'created' => null],
                    ];
                }
                if ($key === 'valid_tickets') {
                    return [TicketData::fromStrings('T-100', 'known_user', 'Valid Ticket')];
                }
                return $default;
            });

        $unknownUsers = $this->sessionManager->getUnknownUsers();
        $validTickets = $this->sessionManager->getValidTickets();

        $this->assertCount(2, $unknownUsers);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $unknownUsers[0]);
        $this->assertEquals('unknown1', $unknownUsers[0]->getUsernameString());
        $this->assertEquals([TicketData::fromStrings('T-100', 'known_user', 'Valid Ticket')], $validTickets);

        $this->sessionManager->clearUploadData();
    }

    public function testStoreTestEmailWithValidEmail(): void
    {
        $testEmail = 'test@example.com';
        
        $this->session->expects($this->once())
            ->method('set')
            ->with('test_email', $testEmail);

        $this->sessionManager->storeTestEmail($testEmail);
    }

    public function testStoreTestEmailWithEmptyString(): void
    {
        $this->session->expects($this->once())
            ->method('remove')
            ->with('test_email');

        $this->sessionManager->storeTestEmail('');
    }

    public function testStoreTestEmailWithNull(): void
    {
        $this->session->expects($this->once())
            ->method('remove')
            ->with('test_email');

        $this->sessionManager->storeTestEmail(null);
    }

    public function testStoreTestEmailTrimsWhitespace(): void
    {
        $testEmail = '  test@example.com  ';
        
        $this->session->expects($this->once())
            ->method('set')
            ->with('test_email', 'test@example.com');

        $this->sessionManager->storeTestEmail($testEmail);
    }

    public function testGetTestEmailReturnsStoredValue(): void
    {
        $testEmail = 'test@example.com';
        
        $this->session->method('get')
            ->with('test_email')
            ->willReturn($testEmail);

        $result = $this->sessionManager->getTestEmail();
        
        $this->assertEquals($testEmail, $result);
    }

    public function testGetTestEmailReturnsNullWhenNotSet(): void
    {
        $this->session->method('get')
            ->with('test_email')
            ->willReturn(null);

        $result = $this->sessionManager->getTestEmail();
        
        $this->assertNull($result);
    }
}