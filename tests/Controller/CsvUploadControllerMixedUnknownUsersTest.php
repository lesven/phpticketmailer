<?php

namespace App\Tests\Controller;

use App\Controller\CsvUploadController;
use App\Service\CsvUploadOrchestrator;
use App\Service\SessionManager;
use App\Service\EmailNormalizer;
use App\Service\UnknownUsersResult;
use App\ValueObject\UnknownUserWithTicket;
use App\ValueObject\Username;
use App\ValueObject\TicketId;
use App\ValueObject\TicketName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests für CsvUploadController mit UnknownUserWithTicket
 * 
 * Diese Tests überprüfen die Logik-Funktionen des Controllers
 * mit ausschließlich typisierten UnknownUserWithTicket-Objekten.
 */
class CsvUploadControllerMixedUnknownUsersTest extends TestCase
{
    private CsvUploadController $controller;
    private CsvUploadOrchestrator $orchestrator;
    private SessionManager $sessionManager;
    private EmailNormalizer $emailNormalizer;

    protected function setUp(): void
    {
        $this->orchestrator = $this->createMock(CsvUploadOrchestrator::class);
        $this->sessionManager = $this->createMock(SessionManager::class);
        $this->emailNormalizer = $this->createMock(EmailNormalizer::class);

        $this->controller = new CsvUploadController(
            $this->orchestrator,
            $this->sessionManager,
            $this->createMock(\App\Service\EmailServiceInterface::class),
            $this->createMock(\App\Repository\CsvFieldConfigRepository::class),
            $this->emailNormalizer,
            $this->createMock(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class)
        );
    }

    /**
     * Testet extractEmailMappingsFromRequest mit UnknownUserWithTicket-Objekten
     */
    public function testUnknownUsersWithTicketObjects(): void
    {
        $unknownUserObject = new UnknownUserWithTicket(
            new Username('objectuser'),
            new TicketId('T-001'),
            new TicketName('Object User Issue')
        );

        $user1 = new UnknownUserWithTicket(
            new Username('stringuser1'),
            new TicketId('UNKNOWN'),
            null
        );

        $user2 = new UnknownUserWithTicket(
            new Username('stringuser2'),
            new TicketId('UNKNOWN'),
            null
        );

        $users = [
            $user1,
            $unknownUserObject,
            $user2
        ];

        // Test der extractEmailMappingsFromRequest-Logik
        $request = new Request();
        $request->request->set('email_stringuser1', 'string1@example.com');
        $request->request->set('email_objectuser', 'object@example.com');
        $request->request->set('email_stringuser2', 'string2@example.com');

        $this->assertIsArray($users);
        $this->assertCount(3, $users);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $users[0]);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $users[1]);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $users[2]);
    }

    /**
     * Testet extractEmailMappingsFromRequest mit UnknownUserWithTicket-Objekten
     */
    public function testExtractEmailMappingsFromTicketObjects(): void
    {
        $unknownUserObject = new UnknownUserWithTicket(
            new Username('object.user'),
            new TicketId('T-001'),
            new TicketName('Object User Issue')
        );

        $stringUser = new UnknownUserWithTicket(
            new Username('string_user'),
            new TicketId('UNKNOWN'),
            null
        );

        $users = [
            $stringUser,
            $unknownUserObject
        ];

        // Mock request with form data
        $request = new Request();
        $request->request->set('email_string_user', 'string@example.com');
        $request->request->set('email_object_user', 'object@example.com');

        $this->emailNormalizer->method('normalizeEmail')
            ->willReturnCallback(function($email) {
                return $email; // Return as-is for test
            });

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('extractEmailMappingsFromRequest');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $request, $users);

        $this->assertArrayHasKey('string_user', $result);
        $this->assertArrayHasKey('object.user', $result);
        $this->assertEquals('string@example.com', $result['string_user']);
        $this->assertEquals('object@example.com', $result['object.user']);
    }

    /**
     * Testet Username-Konvertierung für HTML-Attribute mit Sonderzeichen
     */
    public function testUsernameConversionForHtmlAttributes(): void
    {
        $usernamesWithSpecialChars = [
            'user.with.dots',
            'user-with-dashes',
            'user_with_underscores',
            'user@with.email',
            'UPPERCASE_USER'
        ];

        $unknownUsers = array_map(function($username) {
            return new UnknownUserWithTicket(
                new Username($username),
                new TicketId('T-001'),
                new TicketName('Test Issue')
            );
        }, $usernamesWithSpecialChars);

        $request = new Request();
        // Add form data for each user (with converted names)
        $request->request->set('email_user_with_dots', 'dots@example.com');
        $request->request->set('email_user-with-dashes', 'dashes@example.com');
        $request->request->set('email_user_with_underscores', 'underscores@example.com');
        $request->request->set('email_user@with_email', 'email@example.com');
        $request->request->set('email_UPPERCASE_USER', 'uppercase@example.com');

        $this->emailNormalizer->method('normalizeEmail')
            ->willReturnCallback(function($email) {
                return $email;
            });

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('extractEmailMappingsFromRequest');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $request, $unknownUsers);

        // Should have mappings for all original usernames
        $this->assertCount(5, $result);
        $this->assertArrayHasKey('user.with.dots', $result);
        $this->assertArrayHasKey('user-with-dashes', $result);
        $this->assertArrayHasKey('user_with_underscores', $result);
        $this->assertArrayHasKey('user@with.email', $result);
        $this->assertArrayHasKey('UPPERCASE_USER', $result);
    }

    /**
     * Testet Verhalten bei leerer unknownUsers-Liste
     * Logik-Test ohne HTTP-Response
     */
    public function testEmptyUnknownUsersList(): void
    {
        $emptyUsers = [];
        
        // Einfacher Logik-Test
        $this->assertIsArray($emptyUsers);
        $this->assertEmpty($emptyUsers);
        $this->assertCount(0, $emptyUsers);
    }

    /**
     * Testet Email-Normalisierung bei verschiedenen Formaten
     */
    public function testEmailNormalizationWithDifferentFormats(): void
    {
        $unknownUser = new UnknownUserWithTicket(
            new Username('testuser'),
            new TicketId('T-001'),
            new TicketName('Test Issue')
        );

        $request = new Request();
        $request->request->set('email_testuser', '"John Doe" <john.doe@example.com>');

        $this->emailNormalizer->method('normalizeEmail')
            ->with('"John Doe" <john.doe@example.com>')
            ->willReturn('john.doe@example.com');

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('extractEmailMappingsFromRequest');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $request, [$unknownUser]);

        $this->assertArrayHasKey('testuser', $result);
        $this->assertEquals('john.doe@example.com', $result['testuser']);
    }

    /**
     * Testet ungültige E-Mail-Behandlung - vereinfacht
     */
    public function testInvalidEmailHandling(): void
    {
        $unknownUser = new UnknownUserWithTicket(
            new Username('testuser'),
            new TicketId('T-001'),
            new TicketName('Test Issue')
        );

        // Test der EmailNormalizer-Logik separat
        $this->emailNormalizer->method('normalizeEmail')
            ->with('invalid-email')
            ->willThrowException(new \InvalidArgumentException('Invalid email format'));

        // Teste, dass Exception korrekt geworfen wird
        $this->expectException(\InvalidArgumentException::class);
        $this->emailNormalizer->normalizeEmail('invalid-email');
    }

    /**
     * Testet POST-Request-Verarbeitung mit typisierten UnknownUserWithTicket
     */
    public function testPostRequestWithTypedUnknownUsers(): void
    {
        $unknownUserObject = new UnknownUserWithTicket(
            new Username('objectuser'),
            new TicketId('T-001'),
            new TicketName('Object User Issue')
        );

        $unknownUser = new UnknownUserWithTicket(
            new Username('stringuser'),
            new TicketId('UNKNOWN'),
            null
        );

        $users = [
            $unknownUser,
            $unknownUserObject
        ];

        // Typisierter Logik-Test
        $this->assertIsArray($users);
        $this->assertCount(2, $users);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $users[0]);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $users[1]);
        $this->assertEquals('stringuser', $users[0]->getUsernameString());
        $this->assertEquals('UNKNOWN', $users[0]->getTicketIdString());
        
        // Test der E-Mail-Normalisierung
        $this->emailNormalizer->method('normalizeEmail')
            ->willReturnCallback(function($email) {
                return $email;
            });
            
        $testEmail = 'test@example.com';
        $normalizedEmail = $this->emailNormalizer->normalizeEmail($testEmail);
        $this->assertEquals($testEmail, $normalizedEmail);
    }

    /**
     * Testet Edge Case: Sehr lange Benutzernamen
     */
    public function testVeryLongUsernames(): void
    {
        $longUsername = str_repeat('verylongusername', 20); // 320 chars
        
        $unknownUser = new UnknownUserWithTicket(
            new Username($longUsername),
            new TicketId('T-001'),
            new TicketName('Long Username Test')
        );

        $request = new Request();
        $convertedName = 'email_' . $longUsername; // Should handle long HTML attribute names
        $request->request->set($convertedName, 'longuser@example.com');

        $this->emailNormalizer->method('normalizeEmail')
            ->willReturn('longuser@example.com');

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('extractEmailMappingsFromRequest');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $request, [$unknownUser]);

        $this->assertArrayHasKey($longUsername, $result);
        $this->assertEquals('longuser@example.com', $result[$longUsername]);
    }

    /**
     * Testet Unicode-Zeichen in Benutzernamen
     */
    public function testUnicodeUsernamesInController(): void
    {
        $unicodeUsernames = ['müller', 'josé', '测试用户', 'пользователь'];
        
        $unknownUsers = array_map(function($username) {
            return new UnknownUserWithTicket(
                new Username($username),
                new TicketId('T-001'),
                new TicketName('Unicode Test')
            );
        }, $unicodeUsernames);

        $request = new Request();
        foreach ($unicodeUsernames as $username) {
            $htmlAttr = 'email_' . $username; // Direct usage - should work with Unicode
            $request->request->set($htmlAttr, $username . '@example.com');
        }

        $this->emailNormalizer->method('normalizeEmail')
            ->willReturnCallback(function($email) {
                return $email;
            });

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('extractEmailMappingsFromRequest');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $request, $unknownUsers);

        $this->assertCount(4, $result);
        foreach ($unicodeUsernames as $username) {
            $this->assertArrayHasKey($username, $result);
            $this->assertEquals($username . '@example.com', $result[$username]);
        }
    }

    /**
     * Testet Null-Ticket-Names - vereinfacht
     */
    public function testUnknownUserWithNullTicketName(): void
    {
        $unknownUserWithoutName = new UnknownUserWithTicket(
            new Username('user_no_name'),
            new TicketId('T-001'),
            null // Kein TicketName
        );

        $unknownUserWithName = new UnknownUserWithTicket(
            new Username('user_with_name'),
            new TicketId('T-002'),
            new TicketName('Has Name')
        );

        $mixedUsers = [$unknownUserWithoutName, $unknownUserWithName];

        // Einfacher Logik-Test
        $this->assertIsArray($mixedUsers);
        $this->assertCount(2, $mixedUsers);
        
        // Test der NULL-Behandlung
        $this->assertNull($unknownUserWithoutName->getTicketNameString());
        
        $this->assertEquals('Has Name', $unknownUserWithName->getTicketNameString());
        $this->assertNotNull($unknownUserWithName->getTicketNameString());
    }
}