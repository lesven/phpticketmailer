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
        $reservedUser = $this->expectException(InvalidUsernameException::class);
        
        $this->assertFalse($adminUser->isReserved());
        
        // This should throw an exception during creation
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

    public function validUsernameProvider(): array
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

    public function invalidUsernameProvider(): array
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
            ['user@domain', 'invalid characters'], // Contains @
            ['user space', 'invalid characters'], // Contains space
            ['user<script>', 'invalid characters'], // Contains HTML
            ['user;drop', 'invalid characters'], // SQL injection attempt
            ['user|cmd', 'invalid characters'], // Command injection
            ['../user', 'invalid characters'], // Path traversal
            ['user\\path', 'invalid characters'], // Path traversal
            ['javascript:alert', 'invalid characters'], // XSS
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
}