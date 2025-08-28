<?php

namespace App\Tests\ValueObject;

use App\ValueObject\Username;
use App\Exception\InvalidUsernameException;
use PHPUnit\Framework\TestCase;

class UsernameTest extends TestCase
{
    public function testCreateFromValidString(): void
    {
        $username = Username::fromString('john_doe');
        
        $this->assertEquals('john_doe', $username->getValue());
        $this->assertEquals('john_doe', (string) $username);
    }

    public function testCreateFromStringTrimsAndNormalizes(): void
    {
        $username = Username::fromString('  JOHN.DOE  ');
        
        $this->assertEquals('john.doe', $username->getValue());
    }

    public function testGetDisplayName(): void
    {
        $username = Username::fromString('john_doe');
        
        $this->assertEquals('John_doe', $username->getDisplayName());
    }

    public function testEquality(): void
    {
        $username1 = Username::fromString('john');
        $username2 = Username::fromString('JOHN');  // Case insensitive
        $username3 = Username::fromString('jane');
        
        $this->assertTrue($username1->equals($username2));
        $this->assertFalse($username1->equals($username3));
    }

    public function testIsReserved(): void
    {
        $adminUser = Username::fromString('user123');
        
        $this->assertFalse($adminUser->isReserved());
        
        // This should throw an exception during creation
        $this->expectException(InvalidUsernameException::class);
        Username::fromString('admin');
    }

    public function testContains(): void
    {
        $username = Username::fromString('john_doe');
        
        $this->assertTrue($username->contains('john'));
        $this->assertTrue($username->contains('doe'));
        $this->assertFalse($username->contains('jane'));
    }

    public function testGetLength(): void
    {
        $username = Username::fromString('john');
        
        $this->assertEquals(4, $username->getLength());
    }

    public function testIsNumericOnly(): void
    {
        $numericUser = Username::fromString('123456');
        $mixedUser = Username::fromString('user123');
        
        $this->assertTrue($numericUser->isNumericOnly());
        $this->assertFalse($mixedUser->isNumericOnly());
    }

    /**
     * @dataProvider validUsernameProvider
     */
    public function testValidUsernames(string $username): void
    {
        $user = Username::fromString($username);
        $this->assertInstanceOf(Username::class, $user);
    }

    public static function validUsernameProvider(): array
    {
        return [
            ['user123'],
            ['john.doe'],
            ['jane_smith'],
            ['test-user'],
            ['a1b2c3'],
            ['user'],
            ['123456'],
            ['my.long.username.with.dots'],
            ['user_with_underscores'],
            ['user-with-hyphens'],
            [str_repeat('a', 50)], // Max length
            // E-Mail-Adressen
            ['user@example.com'],
            ['test.email@domain.org'],
            ['user123@test-domain.co.uk'],
            ['User@Example.COM'], // Groß-/Kleinschreibung beibehalten
        ];
    }

    /**
     * @dataProvider invalidUsernameProvider
     */
    public function testInvalidUsernames(string $username, ?string $expectedMessage = null): void
    {
        $this->expectException(InvalidUsernameException::class);
        if ($expectedMessage) {
            $this->expectExceptionMessage($expectedMessage);
        }
        Username::fromString($username);
    }

    public static function invalidUsernameProvider(): array
    {
        return [
            ['', 'cannot be empty'],
            ['a', 'at least 2 characters'], // Too short
            [str_repeat('a', 51), 'must not exceed 50 characters'], // Too long
            ['.user', 'cannot start or end'], // Starts with dot
            ['user.', 'cannot start or end'], // Ends with dot
            ['_user', 'cannot start or end'], // Starts with underscore
            ['user_', 'cannot start or end'], // Ends with underscore
            ['-user', 'cannot start or end'], // Starts with hyphen
            ['user-', 'cannot start or end'], // Ends with hyphen
            ['admin', 'is reserved'], // Reserved name
            ['root', 'is reserved'], // Reserved name
            ['system', 'is reserved'], // Reserved name
            ['user space', 'invalid characters'], // Contains space
            ['user<script>', 'invalid characters'], // Contains HTML
            ['user;drop', 'invalid characters'], // SQL injection attempt
            ['user|cmd', 'invalid characters'], // Command injection
            ['../user', 'invalid characters'], // Path traversal
            ['user\\path', 'invalid characters'], // Path traversal
            ['javascript:alert', 'invalid characters'], // XSS
            // Ungültige E-Mail-Formate
            ['user@', 'Invalid email address format'], // Unvollständige E-Mail
            ['@domain.com', 'Invalid email address format'], // Fehlender Local-Part
            ['user..email@domain.com', 'Invalid email address format'], // Doppelte Punkte
            ['user@domain', 'Invalid email address format'], // Fehlende TLD
            ['user@domain..com', 'Invalid email address format'], // Doppelte Punkte in Domain
        ];
    }

    public function testEmptyUsernameThrowsException(): void
    {
        $this->expectException(InvalidUsernameException::class);
        $this->expectExceptionMessage('Username cannot be empty');
        Username::fromString('');
    }

    public function testNormalizationRemovesConsecutiveSeparators(): void
    {
        $username = Username::fromString('user..name');

        $this->assertEquals('user.name', $username->getValue());
    }

    public function testEmailPreservesCase(): void
    {
        $email = Username::fromString('User@Example.COM');

        $this->assertEquals('User@Example.COM', $email->getValue());
    }

    public function testEmailValidation(): void
    {
        $validEmail = Username::fromString('user@example.com');
        $this->assertInstanceOf(Username::class, $validEmail);

        $this->expectException(InvalidUsernameException::class);
        $this->expectExceptionMessage('Invalid email address format');
        Username::fromString('invalid@');
    }

    public function testReservedNamesDoNotApplyToEmails(): void
    {
        // E-Mail-Adressen sollten nicht gegen reservierte Namen geprüft werden
        $email = Username::fromString('admin@example.com');
        $this->assertInstanceOf(Username::class, $email);
        $this->assertFalse($email->isReserved());
    }

    public function testEmailDisplayName(): void
    {
        $email = Username::fromString('user@example.com');

        $this->assertEquals('User@example.com', $email->getDisplayName());
    }

    public function testEmailEqualityCaseSensitive(): void
    {
        $email1 = Username::fromString('user@example.com');
        $email2 = Username::fromString('User@Example.COM');
        $email3 = Username::fromString('user@example.com');

        // E-Mail-Adressen sollten case-sensitive sein
        $this->assertFalse($email1->equals($email2));
        $this->assertTrue($email1->equals($email3));
    }
}