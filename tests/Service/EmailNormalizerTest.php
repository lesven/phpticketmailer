<?php

namespace App\Tests\Service;

use App\Service\EmailNormalizer;
use PHPUnit\Framework\TestCase;

class EmailNormalizerTest extends TestCase
{
    private EmailNormalizer $emailNormalizer;

    protected function setUp(): void
    {
        $this->emailNormalizer = new EmailNormalizer();
    }

    public function testNormalizeStandardEmail(): void
    {
        $result = $this->emailNormalizer->normalizeEmail('test@example.com');
        $this->assertEquals('test@example.com', $result);
    }

    public function testNormalizeOutlookFormat(): void
    {
        $result = $this->emailNormalizer->normalizeEmail('"Mustermann, Max <max.mustermann@example.com>"');
        $this->assertEquals('max.mustermann@example.com', $result);
    }

    public function testNormalizeOutlookFormatSimple(): void
    {
        $result = $this->emailNormalizer->normalizeEmail('Max Mustermann <max.mustermann@example.com>');
        $this->assertEquals('max.mustermann@example.com', $result);
    }

    public function testNormalizeOutlookFormatWithQuotes(): void
    {
        $result = $this->emailNormalizer->normalizeEmail('"Mustermann, Max" <max.mustermann@example.com>');
        $this->assertEquals('max.mustermann@example.com', $result);
    }

    public function testNormalizeEmptyInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('E-Mail-Eingabe darf nicht leer sein');
        $this->emailNormalizer->normalizeEmail('');
    }

    public function testNormalizeInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültiges E-Mail-Format');
        $this->emailNormalizer->normalizeEmail('not-an-email');
    }

    public function testNormalizeInvalidEmailInOutlookFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültige E-Mail-Adresse in spitzen Klammern gefunden');
        $this->emailNormalizer->normalizeEmail('Max Mustermann <not-an-email>');
    }

    public function testIsOutlookFormat(): void
    {
        $this->assertTrue($this->emailNormalizer->isOutlookFormat('Max Mustermann <max@example.com>'));
        $this->assertTrue($this->emailNormalizer->isOutlookFormat('"Mustermann, Max" <max@example.com>'));
        $this->assertFalse($this->emailNormalizer->isOutlookFormat('max@example.com'));
        $this->assertFalse($this->emailNormalizer->isOutlookFormat('not-an-email'));
    }

    public function testIsStandardEmailFormat(): void
    {
        $this->assertTrue($this->emailNormalizer->isStandardEmailFormat('test@example.com'));
        $this->assertTrue($this->emailNormalizer->isStandardEmailFormat('test.user+tag@example.co.uk'));
        $this->assertFalse($this->emailNormalizer->isStandardEmailFormat('not-an-email'));
        $this->assertFalse($this->emailNormalizer->isStandardEmailFormat('Max <test@example.com>'));
    }

    public function testIsValidEmailInput(): void
    {
        // Standard-E-Mails
        $this->assertTrue($this->emailNormalizer->isValidEmailInput('test@example.com'));
        
        // Outlook-Format
        $this->assertTrue($this->emailNormalizer->isValidEmailInput('Max Mustermann <test@example.com>'));
        $this->assertTrue($this->emailNormalizer->isValidEmailInput('"Mustermann, Max" <test@example.com>'));
        
        // Ungültige Formate
        $this->assertFalse($this->emailNormalizer->isValidEmailInput('not-an-email'));
        $this->assertFalse($this->emailNormalizer->isValidEmailInput('Max <not-an-email>'));
        $this->assertFalse($this->emailNormalizer->isValidEmailInput(''));
    }

    public function testNormalizeWithWhitespace(): void
    {
        $result = $this->emailNormalizer->normalizeEmail('  test@example.com  ');
        $this->assertEquals('test@example.com', $result);
        
        $result = $this->emailNormalizer->normalizeEmail('  Max Mustermann <test@example.com>  ');
        $this->assertEquals('test@example.com', $result);
    }

    public function testComplexOutlookFormats(): void
    {
        // Mit Komma im Namen
        $result = $this->emailNormalizer->normalizeEmail('Mustermann, Dr. Max <max.mustermann@example.com>');
        $this->assertEquals('max.mustermann@example.com', $result);
        
        // Mit Sonderzeichen im Namen
        $result = $this->emailNormalizer->normalizeEmail('Müller-Schmidt, Anna-Maria <anna.mueller@example.com>');
        $this->assertEquals('anna.mueller@example.com', $result);
        
        // Mit Titel
        $result = $this->emailNormalizer->normalizeEmail('Prof. Dr. Schmidt <schmidt@university.edu>');
        $this->assertEquals('schmidt@university.edu', $result);
    }
}
