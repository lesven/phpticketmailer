<?php

namespace App\Tests\ValueObject;

use App\ValueObject\EmailAddress;
use App\Exception\InvalidEmailAddressException;
use PHPUnit\Framework\TestCase;

class EmailAddressTest extends TestCase
{
    public function testValidEmailAddress(): void
    {
        $email = EmailAddress::fromString('test@example.com');
        
        $this->assertEquals('test@example.com', $email->getValue());
        $this->assertEquals('test', $email->getLocalPart());
        $this->assertEquals('example.com', $email->getDomain());
        $this->assertEquals('test@example.com', (string) $email);
    }

    public function testEmailNormalization(): void
    {
        $email = EmailAddress::fromString('  TEST@EXAMPLE.COM  ');
        
        $this->assertEquals('test@example.com', $email->getValue());
    }

    public function testEmailEquality(): void
    {
        $email1 = EmailAddress::fromString('test@example.com');
        $email2 = EmailAddress::fromString('test@example.com');
        $email3 = EmailAddress::fromString('other@example.com');
        
        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }

    public function testBusinessEmailDetection(): void
    {
        $businessEmail = EmailAddress::fromString('contact@company.com');
        $personalEmail = EmailAddress::fromString('user@gmail.com');
        
        $this->assertTrue($businessEmail->isBusinessEmail());
        $this->assertFalse($personalEmail->isBusinessEmail());
    }

    /**
     * @dataProvider tldProvider
     */
    public function testGetTLD(string $email, string $expectedTLD): void
    {
        $emailAddress = EmailAddress::fromString($email);
        $this->assertEquals($expectedTLD, $emailAddress->getTLD());
    }

    public static function tldProvider(): array
    {
        return [
            ['user@example.com', 'com'],
            ['test@company.de', 'de'],
            ['admin@mail.co.uk', 'uk'],
            ['contact@subdomain.company.org', 'org'],
            ['info@example.net', 'net'],
            ['support@service.io', 'io'],
            ['user@domain.info', 'info'],
        ];
    }

    /**
     * @dataProvider invalidEmailProvider
     */
    public function testInvalidEmailAddress(string $invalidEmail): void
    {
        $this->expectException(InvalidEmailAddressException::class);
        EmailAddress::fromString($invalidEmail);
    }

    public static function invalidEmailProvider(): array
    {
        return [
            [''],
            ['invalid'],
            ['invalid@'],
            ['@invalid.com'],
            ['inv@lid@example.com'],
            ['test@temp-mail.org'], // Blocked domain
            ['test@invalid'],
            ['test@.com'],
            ['test@com.'],
            [str_repeat('a', 320) . '@example.com'], // Too long
        ];
    }

    /**
     * @dataProvider validEmailProvider
     */
    public function testValidEmailAddresses(string $validEmail): void
    {
        $email = EmailAddress::fromString($validEmail);
        $this->assertInstanceOf(EmailAddress::class, $email);
    }

    public static function validEmailProvider(): array
    {
        return [
            ['test@example.com'],
            ['user.name@example.com'],
            ['user+tag@example.com'],
            ['user123@example123.com'],
            // ['тест@example.com'], // Unicode not supported by PHP filter_var
            ['test@example-domain.com'],
            ['a@b.co'],
        ];
    }
}