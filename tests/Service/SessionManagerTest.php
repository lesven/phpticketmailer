<?php

namespace App\Tests\Service;

use App\Service\SessionManager;
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
     * - Überprüft, dass sowohl unbekannte Benutzer als auch gültige Tickets gespeichert werden
     * - Testet die korrekte Anzahl der Session-Set-Aufrufe
     */
    public function testStoreUploadResultsWithCompleteData(): void
    {
        $processingResult = [
            'unknownUsers' => ['user1', 'user2', 'user3'],
            'validTickets' => [
                TicketData::fromStrings('T-001', 'john', 'Issue 1'),
                TicketData::fromStrings('T-002', 'jane', 'Issue 2')
            ]
        ];

        $this->session->expects($this->exactly(2))
            ->method('set');

        $this->sessionManager->storeUploadResults($processingResult);
    }

    /**
     * Testet das Speichern unvollständiger Upload-Ergebnisse (nur unbekannte Benutzer)
     * - Überprüft das Verhalten bei fehlenden Datenfeldern
     * - Testet die Robustheit bei partiellen Eingabedaten
     */
    public function testStoreUploadResultsWithPartialData(): void
    {
        $processingResult = [
            'unknownUsers' => ['user1']
            // validTickets fehlt absichtlich
        ];

        $this->session->expects($this->exactly(2))
            ->method('set')
;

        $this->sessionManager->storeUploadResults($processingResult);
    }

    public function testStoreUploadResultsWithEmptyData(): void
    {
        $processingResult = [];

        $this->session->expects($this->exactly(2))
            ->method('set')
;

        $this->sessionManager->storeUploadResults($processingResult);
    }

    public function testStoreUploadResultsWithNullValues(): void
    {
        $processingResult = [
            'unknownUsers' => null,
            'validTickets' => null
        ];

        $this->session->expects($this->exactly(2))
            ->method('set')
;

        $this->sessionManager->storeUploadResults($processingResult);
    }

    public function testGetUnknownUsersReturnsStoredData(): void
    {
        $expectedUsers = ['user1', 'user2', 'user3'];

        $this->session->expects($this->once())
            ->method('get')
            ->with('unknown_users', [])
            ->willReturn($expectedUsers);

        $result = $this->sessionManager->getUnknownUsers();

        $this->assertEquals($expectedUsers, $result);
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
            ->method('remove')
;

        $this->sessionManager->clearUploadData();
    }

    public function testConstructorAcceptsRequestStack(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $sessionManager = new SessionManager($requestStack);

        $this->assertInstanceOf(SessionManager::class, $sessionManager);
    }

    public function testSessionConstantsAreUsedConsistently(): void
    {
        // Test that the same keys are used in store and get operations
        $processingResult = [
            'unknownUsers' => ['test_user'],
            'validTickets' => [TicketData::fromStrings('T-123', 'user')]
        ];

        // Store data
        $this->session->expects($this->exactly(2))
            ->method('set');

        $this->sessionManager->storeUploadResults($processingResult);

        // Verify the same keys are used for retrieval
        $this->session->method('get')
            ->willReturnCallback(function ($key, $default) {
                if ($key === 'unknown_users') {
                    return ['test_user'];
                }
                if ($key === 'valid_tickets') {
                    return [TicketData::fromStrings('T-123', 'user')];
                }
                return $default;
            });

        $unknownUsers = $this->sessionManager->getUnknownUsers();
        $validTickets = $this->sessionManager->getValidTickets();

        $this->assertEquals(['test_user'], $unknownUsers);
        $this->assertEquals([TicketData::fromStrings('T-123', 'user')], $validTickets);
    }

    public function testMultipleStoreOperationsOverwritePreviousData(): void
    {
        // First store operation
        $firstResult = [
            'unknownUsers' => ['user1'],
            'validTickets' => [TicketData::fromStrings('T-001', 'user1')]
        ];

        $this->session->expects($this->exactly(4))
            ->method('set');

        $this->sessionManager->storeUploadResults($firstResult);

        // Second store operation should overwrite
        $secondResult = [
            'unknownUsers' => ['user2', 'user3'],
            'validTickets' => [
                TicketData::fromStrings('T-002', 'user2'),
                TicketData::fromStrings('T-003', 'user3')
            ]
        ];

        $this->sessionManager->storeUploadResults($secondResult);
    }

    public function testWorkflowIntegration(): void
    {
        // Simulate a typical workflow: store, retrieve, clear
        $processingResult = [
            'unknownUsers' => ['unknown1', 'unknown2'],
            'validTickets' => [
                TicketData::fromStrings('T-100', 'known_user', 'Valid Ticket')
            ]
        ];

        // 1. Store data
        $this->session->expects($this->exactly(2))
            ->method('set');

        $this->sessionManager->storeUploadResults($processingResult);

        // 2. Retrieve data (simulate multiple calls)
        $this->session->method('get')
            ->willReturnCallback(function ($key, $default) {
                if ($key === 'unknown_users') {
                    return ['unknown1', 'unknown2'];
                }
                if ($key === 'valid_tickets') {
                    return [TicketData::fromStrings('T-100', 'known_user', 'Valid Ticket')];
                }
                return $default;
            });

        $unknownUsers = $this->sessionManager->getUnknownUsers();
        $validTickets = $this->sessionManager->getValidTickets();
        $unknownUsersAgain = $this->sessionManager->getUnknownUsers();

        $this->assertEquals(['unknown1', 'unknown2'], $unknownUsers);
        $this->assertEquals([TicketData::fromStrings('T-100', 'known_user', 'Valid Ticket')], $validTickets);
        $this->assertEquals(['unknown1', 'unknown2'], $unknownUsersAgain);

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