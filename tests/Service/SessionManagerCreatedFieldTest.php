<?php
/**
 * Test backwards compatibility of SessionManager with created field
 * This simulates old session data without the created field
 */

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\SessionManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionManagerCreatedFieldTest extends TestCase
{
    public function testBackwardsCompatibilityWithOldSessionData(): void
    {
        // Simulate old session data without 'created' field
        $oldSessionData = [
            [
                'type' => 'UnknownUserWithTicket',
                'username' => 'user1',
                'ticketId' => 'T-001',
                'ticketName' => 'Test Ticket'
                // Note: no 'created' field
            ],
            [
                'type' => 'string',
                'username' => 'user2'
            ]
        ];

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('get')
            ->with('unknown_users', [])
            ->willReturn($oldSessionData);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($sessionMock);

        $sessionManager = new SessionManager($requestStack);
        $result = $sessionManager->getUnknownUsers();

        $this->assertCount(2, $result);
        $this->assertInstanceOf(\App\ValueObject\UnknownUserWithTicket::class, $result[0]);
        $this->assertEquals('user1', $result[0]->getUsernameString());
        $this->assertNull($result[0]->getCreatedString()); // Should be null for old data
        $this->assertEquals('user2', $result[1]); // String fallback
    }

    public function testNewSessionDataWithCreatedField(): void
    {
        // New session data with 'created' field
        $newSessionData = [
            [
                'type' => 'UnknownUserWithTicket',
                'username' => 'user1',
                'ticketId' => 'T-001',
                'ticketName' => 'Test Ticket',
                'created' => '2024-01-15'
            ]
        ];

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('get')
            ->with('unknown_users', [])
            ->willReturn($newSessionData);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($sessionMock);

        $sessionManager = new SessionManager($requestStack);
        $result = $sessionManager->getUnknownUsers();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(\App\ValueObject\UnknownUserWithTicket::class, $result[0]);
        $this->assertEquals('user1', $result[0]->getUsernameString());
        $this->assertEquals('2024-01-15', $result[0]->getCreatedString());
    }
}
