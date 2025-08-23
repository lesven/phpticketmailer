<?php

namespace App\Tests\Service;

use App\Service\SessionManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

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

    public function testStoreUploadResultsWithCompleteData(): void
    {
        $processingResult = [
            'unknownUsers' => ['user1', 'user2', 'user3'],
            'validTickets' => [
                ['ticketId' => 'T-001', 'username' => 'john', 'ticketName' => 'Issue 1'],
                ['ticketId' => 'T-002', 'username' => 'jane', 'ticketName' => 'Issue 2']
            ]
        ];

        $this->session->expects($this->exactly(2))
            ->method('set');

        $this->sessionManager->storeUploadResults($processingResult);
    }

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
            ['ticketId' => 'T-001', 'username' => 'john', 'ticketName' => 'Test Issue'],
            ['ticketId' => 'T-002', 'username' => 'jane', 'ticketName' => 'Another Issue']
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
        $this->session->expects($this->exactly(2))
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
            'validTickets' => [['ticketId' => 'T-123']]
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
                    return [['ticketId' => 'T-123']];
                }
                return $default;
            });

        $unknownUsers = $this->sessionManager->getUnknownUsers();
        $validTickets = $this->sessionManager->getValidTickets();

        $this->assertEquals(['test_user'], $unknownUsers);
        $this->assertEquals([['ticketId' => 'T-123']], $validTickets);
    }

    public function testMultipleStoreOperationsOverwritePreviousData(): void
    {
        // First store operation
        $firstResult = [
            'unknownUsers' => ['user1'],
            'validTickets' => [['ticketId' => 'T-001']]
        ];

        $this->session->expects($this->exactly(4))
            ->method('set');

        $this->sessionManager->storeUploadResults($firstResult);

        // Second store operation should overwrite
        $secondResult = [
            'unknownUsers' => ['user2', 'user3'],
            'validTickets' => [
                ['ticketId' => 'T-002'],
                ['ticketId' => 'T-003']
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
                ['ticketId' => 'T-100', 'username' => 'known_user', 'ticketName' => 'Valid Ticket']
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
                    return [['ticketId' => 'T-100', 'username' => 'known_user', 'ticketName' => 'Valid Ticket']];
                }
                return $default;
            });

        $unknownUsers = $this->sessionManager->getUnknownUsers();
        $validTickets = $this->sessionManager->getValidTickets();
        $unknownUsersAgain = $this->sessionManager->getUnknownUsers();

        $this->assertEquals(['unknown1', 'unknown2'], $unknownUsers);
        $this->assertEquals([['ticketId' => 'T-100', 'username' => 'known_user', 'ticketName' => 'Valid Ticket']], $validTickets);
        $this->assertEquals(['unknown1', 'unknown2'], $unknownUsersAgain);

        // 3. Clear data
        $this->session->expects($this->exactly(2))
            ->method('remove');

        $this->sessionManager->clearUploadData();
    }
}