<?php

namespace App\Tests\Dto;

use App\Dto\EmailConfig;
use App\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

class EmailConfigTest extends TestCase
{
    public function testConstructorWithStringEmailAddresses(): void
    {
        $config = new EmailConfig(
            subject: 'Ihr Ticket {{ticketId}}',
            ticketBaseUrl: 'https://jira.example.com/browse/',
            senderEmail: 'noreply@example.com',
            senderName: 'Ticket Mailer',
            testEmail: 'test@example.com',
            useCustomSMTP: false,
            smtpDSN: null
        );

        $this->assertSame('Ihr Ticket {{ticketId}}', $config->subject);
        $this->assertSame('https://jira.example.com/browse/', $config->ticketBaseUrl);
        $this->assertSame('noreply@example.com', $config->senderEmail);
        $this->assertSame('Ticket Mailer', $config->senderName);
        $this->assertSame('test@example.com', $config->testEmail);
        $this->assertFalse($config->useCustomSMTP);
        $this->assertNull($config->smtpDSN);
    }

    public function testConstructorWithEmailAddressObjects(): void
    {
        $senderEmail = EmailAddress::fromString('sender@example.com');
        $testEmail = EmailAddress::fromString('test@example.com');

        $config = new EmailConfig(
            subject: 'Ticket Update',
            ticketBaseUrl: 'https://tickets.example.com/',
            senderEmail: $senderEmail,
            senderName: 'System',
            testEmail: $testEmail,
            useCustomSMTP: true,
            smtpDSN: 'smtp://user:pass@mail.example.com:587'
        );

        $this->assertSame($senderEmail, $config->senderEmail);
        $this->assertSame($testEmail, $config->testEmail);
        $this->assertTrue($config->useCustomSMTP);
        $this->assertSame('smtp://user:pass@mail.example.com:587', $config->smtpDSN);
    }

    public function testSmtpDsnDefaultIsNull(): void
    {
        $config = new EmailConfig(
            subject: 'Test',
            ticketBaseUrl: 'http://localhost/',
            senderEmail: 'a@b.com',
            senderName: 'Test',
            testEmail: 'test@b.com',
            useCustomSMTP: false
        );

        $this->assertNull($config->smtpDSN);
    }

    public function testUseCustomSmtpIsStored(): void
    {
        $configCustom = new EmailConfig('s', 'u', 'a@a.com', 'N', 'b@a.com', true, 'dsn://test');
        $configDefault = new EmailConfig('s', 'u', 'a@a.com', 'N', 'b@a.com', false);

        $this->assertTrue($configCustom->useCustomSMTP);
        $this->assertFalse($configDefault->useCustomSMTP);
    }
}
