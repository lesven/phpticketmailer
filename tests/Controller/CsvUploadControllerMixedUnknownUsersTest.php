<?php

namespace App\Tests\Controller;

use App\Controller\CsvUploadController;
use App\Service\CsvUploadOrchestrator;
use App\Service\SessionManager;
use App\Service\EmailNormalizer;
use App\ValueObject\UnknownUserWithTicket;
use App\ValueObject\Username;
use App\ValueObject\TicketId;
use App\ValueObject\TicketName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Tests für CsvUploadController mit gemischten unknown user types
 * 
 * Diese Tests überprüfen, dass der Controller korrekt mit sowohl
 * UnknownUserWithTicket-Objekten als auch String-Fallbacks umgeht.
 */
class CsvUploadControllerMixedUnknownUsersTest extends TestCase
{
    private CsvUploadController $controller;
    private CsvUploadOrchestrator $orchestrator;
    private SessionManager $sessionManager;
    private EmailNormalizer $emailNormalizer;
    private FlashBagInterface $flashBag;

    protected function setUp(): void
    {
        $this->orchestrator = $this->createMock(CsvUploadOrchestrator::class);
        $this->sessionManager = $this->createMock(SessionManager::class);
        $this->emailNormalizer = $this->createMock(EmailNormalizer::class);
        $this->flashBag = $this->createMock(FlashBagInterface::class);

        $this->controller = new CsvUploadController(
            $this->orchestrator,
            $this->sessionManager,
            $this->createMock(\App\Service\EmailService::class),
            $this->createMock(\App\Repository\CsvFieldConfigRepository::class),
            $this->emailNormalizer,
            $this->createMock(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class)
        );

        // Mock the flash bag
        $reflection = new \ReflectionClass($this->controller);
        $addFlashMethod = $reflection->getMethod('addFlash');
        $addFlashMethod->setAccessible(true);
    }

    /**
     * Testet unknownUsers() mit gemischten UnknownUserWithTicket und Strings
     */
    public function testUnknownUsersWithMixedTypes(): void
    {
        $unknownUserObject = new UnknownUserWithTicket(
            new Username('objectuser'),
            new TicketId('T-001'),
            new TicketName('Object User Issue')
        );

        $mixedUsers = [
            'stringuser1',
            $unknownUserObject,
            'stringuser2'
        ];

        $this->sessionManager->method('getUnknownUsers')
            ->willReturn($mixedUsers);

        $request = new Request();
        
        // Mock Twig environment if needed
        $response = $this->controller->unknownUsers($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Testet extractEmailMappingsFromRequest mit gemischten Typen
     */
    public function testExtractEmailMappingsFromMixedTypes(): void
    {
        $unknownUserObject = new UnknownUserWithTicket(
            new Username('object.user'),
            new TicketId('T-001'),
            new TicketName('Object User Issue')
        );

        $mixedUsers = [
            'string_user',
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

        $result = $method->invoke($this->controller, $request, $mixedUsers);

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
     */
    public function testEmptyUnknownUsersList(): void
    {
        $this->sessionManager->method('getUnknownUsers')
            ->willReturn([]);

        $request = new Request();
        $response = $this->controller->unknownUsers($request);

        // Should redirect when no unknown users
        $this->assertEquals(302, $response->getStatusCode());
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
     * Testet Fehlerbehandlung bei ungültigen E-Mails
     */
    public function testInvalidEmailHandling(): void
    {
        $unknownUser = new UnknownUserWithTicket(
            new Username('testuser'),
            new TicketId('T-001'),
            new TicketName('Test Issue')
        );

        $request = new Request();
        $request->request->set('email_testuser', 'invalid-email');

        $this->emailNormalizer->method('normalizeEmail')
            ->with('invalid-email')
            ->willThrowException(new \InvalidArgumentException('Invalid email format'));

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('extractEmailMappingsFromRequest');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $request, [$unknownUser]);

        // Should not include invalid email in result
        $this->assertEmpty($result);
    }

    /**
     * Testet POST-Request-Verarbeitung mit gemischten Typen
     */
    public function testPostRequestWithMixedUnknownUserTypes(): void
    {
        $unknownUserObject = new UnknownUserWithTicket(
            new Username('objectuser'),
            new TicketId('T-001'),
            new TicketName('Object User Issue')
        );

        $mixedUsers = [
            'stringuser',
            $unknownUserObject
        ];

        $this->sessionManager->method('getUnknownUsers')
            ->willReturn($mixedUsers);

        $this->emailNormalizer->method('normalizeEmail')
            ->willReturnCallback(function($email) {
                return $email;
            });

        // Mock successful orchestrator response
        $mockResult = new class {
            public $flashType = 'success';
            public $message = 'Users processed successfully';
        };

        $this->orchestrator->method('processUnknownUsers')
            ->willReturn($mockResult);

        $request = new Request([], [
            'email_stringuser' => 'string@example.com',
            'email_objectuser' => 'object@example.com'
        ]);
        $request->setMethod('POST');

        $response = $this->controller->unknownUsers($request);

        // Should redirect after successful processing
        $this->assertEquals(302, $response->getStatusCode());
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
     * Testet Null-Ticket-Names im Template-Kontext
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

        $this->sessionManager->method('getUnknownUsers')
            ->willReturn($mixedUsers);

        $request = new Request();
        $response = $this->controller->unknownUsers($request);

        $this->assertEquals(200, $response->getStatusCode());
        // Template should handle null ticket names gracefully
    }
}