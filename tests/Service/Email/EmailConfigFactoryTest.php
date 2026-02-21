<?php

namespace App\Tests\Service\Email;

use App\Dto\EmailConfig;
use App\Service\Email\EmailConfigFactory;
use App\Repository\SMTPConfigRepository;
use App\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class EmailConfigFactoryTest extends TestCase
{
    private $smtpRepo;
    private $params;

    protected function setUp(): void
    {
        $this->smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $this->params = $this->createMock(ParameterBagInterface::class);

        $this->params->method('get')->willReturnCallback(function ($key) {
            return match ($key) {
                'app.email_subject' => 'Feedback zu Ticket {{ticketId}}',
                'app.ticket_base_url' => 'https://tickets.example',
                'app.test_email' => 'test@example.com',
                'app.sender_email' => 'noreply@example.com',
                'app.sender_name' => 'Ticket-System',
                default => null,
            };
        });
    }

    public function testCreateWithoutSmtpConfig(): void
    {
        $this->smtpRepo->method('getConfig')->willReturn(null);

        $factory = new EmailConfigFactory($this->smtpRepo, $this->params);
        $config = $factory->create();

        $this->assertInstanceOf(EmailConfig::class, $config);
        $this->assertEquals('Feedback zu Ticket {{ticketId}}', $config->subject);
        $this->assertEquals('https://tickets.example', $config->ticketBaseUrl);
        $this->assertEquals('noreply@example.com', $config->senderEmail);
        $this->assertEquals('Ticket-System', $config->senderName);
        $this->assertFalse($config->useCustomSMTP);
    }

    public function testCreateWithSmtpConfig(): void
    {
        $smtpConfig = $this->createMock(\App\Entity\SMTPConfig::class);
        $smtpConfig->method('getSenderEmail')->willReturn(EmailAddress::fromString('db@example.com'));
        $smtpConfig->method('getSenderName')->willReturn('DB Sender');
        $smtpConfig->method('getTicketBaseUrl')->willReturn('https://db.example');
        $smtpConfig->method('getDSN')->willReturn('smtp://u:p@smtp.example:587');

        $this->smtpRepo->method('getConfig')->willReturn($smtpConfig);

        $factory = new EmailConfigFactory($this->smtpRepo, $this->params);
        $config = $factory->create();

        $this->assertTrue($config->useCustomSMTP);
        $this->assertEquals('https://db.example', $config->ticketBaseUrl);
        $this->assertEquals('DB Sender', $config->senderName);
        $this->assertStringContainsString('smtp://', $config->smtpDSN);
    }

    public function testCreateWithCustomTestEmail(): void
    {
        $this->smtpRepo->method('getConfig')->willReturn(null);

        $factory = new EmailConfigFactory($this->smtpRepo, $this->params);
        $config = $factory->create(true, 'custom@example.com');

        $this->assertEquals('custom@example.com', $config->testEmail);
    }

    public function testCreateWithEmptyCustomTestEmailUsesDefault(): void
    {
        $this->smtpRepo->method('getConfig')->willReturn(null);

        $factory = new EmailConfigFactory($this->smtpRepo, $this->params);
        $config = $factory->create(true, '');

        $this->assertEquals('test@example.com', $config->testEmail);
    }
}
