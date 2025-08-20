<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit-Tests für die User-Entity
 * 
 * Diese Tests überprüfen die Funktionalität der User-Entity,
 * einschließlich Benutzerdaten-Verwaltung, E-Mail-Validation,
 * Eindeutigkeit und verschiedener Edge Cases.
 */
#[CoversClass(User::class)]
class UserTest extends TestCase
{
    private User $user;

    /**
     * Setup für jeden Test
     */
    protected function setUp(): void
    {
        $this->user = new User();
    }

    /**
     * Testet die Initialisierung einer neuen User-Instanz
     */
    public function testInitialization(): void
    {
        $user = new User();
        
        $this->assertNull($user->getId());
        $this->assertNull($user->getUsername());
        $this->assertNull($user->getEmail());
    }

    /**
     * Testet das Setzen und Abrufen des Benutzernamens
     */
    public function testUsernameGetterAndSetter(): void
    {
        $username = 'john.doe';
        
        $result = $this->user->setUsername($username);
        
        $this->assertSame($this->user, $result);
        $this->assertSame($username, $this->user->getUsername());
    }

    /**
     * Testet das Setzen und Abrufen der E-Mail-Adresse
     */
    public function testEmailGetterAndSetter(): void
    {
        $email = 'john.doe@example.com';
        
        $result = $this->user->setEmail($email);
        
        $this->assertSame($this->user, $result);
        $this->assertSame($email, $this->user->getEmail());
    }

    /**
     * Testet das Method-Chaining für alle Setter-Methoden
     */
    public function testMethodChaining(): void
    {
        $result = $this->user
            ->setUsername('testuser')
            ->setEmail('test@example.com');
        
        $this->assertSame($this->user, $result);
        $this->assertSame('testuser', $this->user->getUsername());
        $this->assertSame('test@example.com', $this->user->getEmail());
    }

    /**
     * Testet verschiedene Benutzername-Formate
     */
    public function testDifferentUsernameFormats(): void
    {
        $usernameFormats = [
            'simple' => 'john',
            'with_dot' => 'john.doe',
            'with_underscore' => 'john_doe',
            'with_hyphen' => 'john-doe',
            'with_numbers' => 'john123',
            'mixed' => 'john.doe_123-test',
            'uppercase' => 'JOHN.DOE',
            'lowercase' => 'john.doe',
            'camelcase' => 'johnDoe',
        ];
        
        foreach ($usernameFormats as $description => $username) {
            $this->user->setUsername($username);
            $this->assertSame($username, $this->user->getUsername(), 
                "Failed for username format: {$description}");
        }
    }

    /**
     * Testet verschiedene E-Mail-Adress-Formate
     */
    public function testDifferentEmailFormats(): void
    {
        $emailFormats = [
            'standard' => 'user@example.com',
            'subdomain' => 'user@mail.example.com',
            'plus_addressing' => 'user+tag@example.com',
            'dots_in_local' => 'user.name@example.com',
            'underscores' => 'user_name@example.com',
            'hyphens' => 'user-name@example.com',
            'numbers' => 'user123@example.com',
            'international_domain' => 'user@example.de',
            'long_domain' => 'user@very.long.subdomain.example.com',
        ];
        
        foreach ($emailFormats as $description => $email) {
            $this->user->setEmail($email);
            $this->assertSame($email, $this->user->getEmail(), 
                "Failed for email format: {$description}");
        }
    }

    /**
     * Testet Unicode-Zeichen in Benutzernamen und E-Mails
     */
    public function testUnicodeCharacters(): void
    {
        $this->user
            ->setUsername('müller.äöü')
            ->setEmail('müller@example.de');
        
        $this->assertSame('müller.äöü', $this->user->getUsername());
        $this->assertSame('müller@example.de', $this->user->getEmail());
    }

    /**
     * Testet sehr lange Benutzernamen und E-Mail-Adressen
     */
    public function testLongValues(): void
    {
        $longUsername = str_repeat('user', 60) . '123'; // ~250 Zeichen
        $longEmail = str_repeat('test', 60) . '@example.com'; // ~250 Zeichen
        
        $this->user
            ->setUsername($longUsername)
            ->setEmail($longEmail);
        
        $this->assertSame($longUsername, $this->user->getUsername());
        $this->assertSame($longEmail, $this->user->getEmail());
        $this->assertSame(243, strlen($this->user->getUsername()));
    }

    /**
     * Testet leere String-Werte
     */
    public function testEmptyStringValues(): void
    {
        $this->user
            ->setUsername('')
            ->setEmail('');
        
        $this->assertSame('', $this->user->getUsername());
        $this->assertSame('', $this->user->getEmail());
    }

    /**
     * Testet Whitespace in Benutzernamen und E-Mails
     */
    public function testWhitespaceHandling(): void
    {
        $usernameWithSpaces = ' john.doe ';
        $emailWithSpaces = ' user@example.com ';
        
        $this->user
            ->setUsername($usernameWithSpaces)
            ->setEmail($emailWithSpaces);
        
        // Die Entity sollte Whitespace nicht automatisch trimmen
        $this->assertSame($usernameWithSpaces, $this->user->getUsername());
        $this->assertSame($emailWithSpaces, $this->user->getEmail());
    }

    /**
     * Testet Sonderzeichen in Benutzernamen
     */
    public function testSpecialCharactersInUsername(): void
    {
        $specialUsernames = [
            'user@domain.com', // E-Mail als Username
            'user+tag',
            'user/path',
            'user\\backslash',
            'user#hashtag',
            'user$dollar',
            'user%percent',
            'user&ampersand',
            'user*asterisk',
            'user(parenthesis)',
            'user[bracket]',
            'user{brace}',
            'user|pipe',
            'user;semicolon',
            'user:colon',
            'user"quote',
            "user'apostrophe",
            'user<less>',
            'user?question',
            'user=equal',
        ];
        
        foreach ($specialUsernames as $username) {
            $this->user->setUsername($username);
            $this->assertSame($username, $this->user->getUsername(), 
                "Failed for username with special character: {$username}");
        }
    }

    /**
     * Testet Case-Sensitivity von Benutzernamen und E-Mails
     */
    public function testCaseSensitivity(): void
    {
        $this->user
            ->setUsername('John.Doe')
            ->setEmail('John.Doe@Example.COM');
        
        $this->assertSame('John.Doe', $this->user->getUsername());
        $this->assertSame('John.Doe@Example.COM', $this->user->getEmail());
        
        // Case wird beibehalten
        $this->assertNotSame('john.doe', $this->user->getUsername());
        $this->assertNotSame('john.doe@example.com', $this->user->getEmail());
    }

    /**
     * Testet das Überschreiben von bereits gesetzten Werten
     */
    public function testOverwritingValues(): void
    {
        // Erste Werte setzen
        $this->user
            ->setUsername('firstUser')
            ->setEmail('first@example.com');
        
        $this->assertSame('firstUser', $this->user->getUsername());
        $this->assertSame('first@example.com', $this->user->getEmail());
        
        // Werte überschreiben
        $this->user
            ->setUsername('secondUser')
            ->setEmail('second@example.com');
        
        $this->assertSame('secondUser', $this->user->getUsername());
        $this->assertSame('second@example.com', $this->user->getEmail());
    }

    /**
     * Data Provider für verschiedene Benutzername-Formate
     */
    public static function usernameDataProvider(): array
    {
        return [
            'simple' => ['john'],
            'with_dot' => ['john.doe'],
            'with_underscore' => ['john_doe'],
            'with_hyphen' => ['john-doe'],
            'with_numbers' => ['user123'],
            'email_format' => ['user@domain.com'],
            'mixed_case' => ['JohnDoe'],
            'all_uppercase' => ['JOHNDOE'],
            'all_lowercase' => ['johndoe'],
            'unicode' => ['müller'],
            'with_spaces' => ['john doe'],
            'special_chars' => ['user+tag'],
        ];
    }

    /**
     * Testet verschiedene Benutzername-Formate mit Data Provider
     */
    #[DataProvider('usernameDataProvider')]
    public function testUsernameFormats(string $username): void
    {
        $this->user->setUsername($username);
        $this->assertSame($username, $this->user->getUsername());
    }

    /**
     * Data Provider für verschiedene E-Mail-Formate
     */
    public static function emailDataProvider(): array
    {
        return [
            'standard' => ['user@example.com'],
            'subdomain' => ['user@mail.example.com'],
            'plus_addressing' => ['user+tag@example.com'],
            'dots_in_local' => ['user.name@example.com'],
            'underscores' => ['user_name@example.com'],
            'hyphens_local' => ['user-name@example.com'],
            'numbers' => ['user123@example.com'],
            'international' => ['user@example.de'],
            'unicode_domain' => ['user@münchen.de'],
            'long_local' => ['very.long.email.address@example.com'],
            'mixed_case' => ['User.Name@Example.COM'],
        ];
    }

    /**
     * Testet verschiedene E-Mail-Formate mit Data Provider
     */
    #[DataProvider('emailDataProvider')]
    public function testEmailFormats(string $email): void
    {
        $this->user->setEmail($email);
        $this->assertSame($email, $this->user->getEmail());
    }

    /**
     * Testet Boundary Values für String-Längen
     */
    public function testStringLengthBoundaries(): void
    {
        // Sehr kurze Werte
        $this->user
            ->setUsername('a')
            ->setEmail('a@b.c');
        
        $this->assertSame('a', $this->user->getUsername());
        $this->assertSame('a@b.c', $this->user->getEmail());
        
        // Werte an der 255-Zeichen-Grenze
        $maxUsername = str_repeat('a', 255);
        $maxEmail = str_repeat('a', 240) . '@example.com'; // 252 Zeichen
        
        $this->user
            ->setUsername($maxUsername)
            ->setEmail($maxEmail);
        
        $this->assertSame($maxUsername, $this->user->getUsername());
        $this->assertSame($maxEmail, $this->user->getEmail());
        $this->assertSame(255, strlen($this->user->getUsername()));
        $this->assertSame(252, strlen($this->user->getEmail()));
    }

    /**
     * Testet verschiedene Nationale Zeichen
     */
    public function testInternationalCharacters(): void
    {
        $internationalData = [
            'german' => ['müller', 'müller@example.de'],
            'french' => ['françois', 'françois@example.fr'],
            'spanish' => ['josé', 'josé@example.es'],
            'italian' => ['giuseppe', 'giuseppe@example.it'],
            'scandinavian' => ['øystein', 'øystein@example.no'],
            'slavic' => ['václav', 'václav@example.cz'],
            'greek' => ['αλέξης', 'αλέξης@example.gr'],
            'cyrillic' => ['владимир', 'владимир@example.ru'],
        ];
        
        foreach ($internationalData as $language => [$username, $email]) {
            $this->user
                ->setUsername($username)
                ->setEmail($email);
            
            $this->assertSame($username, $this->user->getUsername(), 
                "Failed for {$language} username");
            $this->assertSame($email, $this->user->getEmail(), 
                "Failed for {$language} email");
        }
    }

    /**
     * Testet die vollständige Benutzer-Konfiguration
     */
    public function testCompleteUserConfiguration(): void
    {
        $username = 'john.doe';
        $email = 'john.doe@company.com';
        
        $this->user
            ->setUsername($username)
            ->setEmail($email);
        
        // Prüft alle Werte
        $this->assertSame($username, $this->user->getUsername());
        $this->assertSame($email, $this->user->getEmail());
        $this->assertNull($this->user->getId()); // ID wird von Doctrine gesetzt
        
        // Prüft Method-Chaining-Resultat
        $chainResult = $this->user->setUsername('new.user')->setEmail('new@example.com');
        $this->assertSame($this->user, $chainResult);
    }

    /**
     * Testet numerische Benutzernamen
     */
    public function testNumericUsernames(): void
    {
        $numericUsernames = ['123', '0', '999999', '123456789'];
        
        foreach ($numericUsernames as $username) {
            $this->user->setUsername($username);
            $this->assertSame($username, $this->user->getUsername());
        }
    }

    /**
     * Testet Konsistenz bei wiederholten Aufrufen
     */
    public function testConsistencyAcrossMultipleCalls(): void
    {
        $username = 'consistent.user';
        $email = 'consistent@example.com';
        
        $this->user
            ->setUsername($username)
            ->setEmail($email);
        
        // Mehrfaches Abrufen sollte identische Ergebnisse liefern
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($username, $this->user->getUsername());
            $this->assertSame($email, $this->user->getEmail());
        }
    }
}
