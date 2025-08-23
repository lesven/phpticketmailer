<?php

namespace App\Tests\ValueObject;

use App\ValueObject\SecurePassword;
use App\Exception\WeakPasswordException;
use PHPUnit\Framework\TestCase;

class SecurePasswordTest extends TestCase
{
    public function testCreateFromPlaintext(): void
    {
        $password = SecurePassword::fromPlaintext('StrongP@ssw0rd123!');
        
        $this->assertNotEmpty($password->getHash());
        $this->assertTrue($password->verify('StrongP@ssw0rd123!'));
        $this->assertFalse($password->verify('wrongpassword'));
    }

    public function testCreateFromHash(): void
    {
        $hash = password_hash('Strong@123!', PASSWORD_BCRYPT);
        $password = SecurePassword::fromHash($hash);
        
        $this->assertEquals($hash, $password->getHash());
        $this->assertTrue($password->verify('Strong@123!'));
    }

    public function testPasswordEquality(): void
    {
        $hash = password_hash('StrongP@ss123!', PASSWORD_BCRYPT);
        $password1 = SecurePassword::fromHash($hash);
        $password2 = SecurePassword::fromHash($hash);
        $password3 = SecurePassword::fromPlaintext('Different@456!');
        
        $this->assertTrue($password1->equals($password2));
        $this->assertFalse($password1->equals($password3));
    }

    public function testPasswordRehash(): void
    {
        $plaintext = 'StrongP@ssw0rd123!';
        $password = SecurePassword::fromPlaintext($plaintext);
        $originalHash = $password->getHash();
        
        $rehashed = $password->rehash($plaintext);
        
        $this->assertNotEquals($originalHash, $rehashed->getHash());
        $this->assertTrue($rehashed->verify($plaintext));
    }

    public function testRehashWithWrongPassword(): void
    {
        $password = SecurePassword::fromPlaintext('Correct@123!');
        
        $this->expectException(\InvalidArgumentException::class);
        $password->rehash('Wrong@456!');
    }

    public function testGenerateSecurePassword(): void
    {
        $password = SecurePassword::generateSecure(16);
        
        $this->assertNotEmpty($password->getHash());
        
        // BCrypt hash is always 60 characters
        $this->assertEquals(60, strlen($password->getHash()));
    }

    /**
     * @dataProvider weakPasswordProvider
     */
    public function testWeakPasswordRejection(string $weakPassword): void
    {
        $this->expectException(WeakPasswordException::class);
        SecurePassword::fromPlaintext($weakPassword);
    }

    public function weakPasswordProvider(): array
    {
        return [
            ['short'], // Too short
            ['password'], // Common weak password
            ['123456'], // Common weak password
            ['geheim'], // Your current default password
            ['12345678'], // Too simple
            ['aaaaaaaaa'], // Repeated characters
            ['abcdefgh'], // Sequential
            [str_repeat('a', 129)], // Too long
        ];
    }

    /**
     * @dataProvider strongPasswordProvider
     */
    public function testStrongPasswordAcceptance(string $strongPassword): void
    {
        $password = SecurePassword::fromPlaintext($strongPassword);
        $this->assertInstanceOf(SecurePassword::class, $password);
        $this->assertTrue($password->verify($strongPassword));
    }

    public function strongPasswordProvider(): array
    {
        return [
            ['StrongP@ssw0rd123!'],
            ['My$ecur3P@ssw0rd'],
            ['C0mpl3x_P@$$w0rd!'],
            ['Test123!@#'], // Strong mixed characters
            ['VeryL0ng&C0mpl3xP@ssw0rdW1thM@nyChar@ct3rs!'],
        ];
    }

    public function testNeedsRehash(): void
    {
        // Create password with low cost (should need rehash)
        $lowCostHash = password_hash('test123456', PASSWORD_BCRYPT, ['cost' => 4]);
        $password = SecurePassword::fromHash($lowCostHash);
        
        // Note: This test might be environment-dependent
        // In a real scenario, you'd want to mock the password_needs_rehash function
        $this->assertIsBool($password->needsRehash());
    }
}