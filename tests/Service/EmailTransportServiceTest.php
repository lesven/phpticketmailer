<?php

namespace App\Tests\Service;

use App\Dto\EmailConfig;
use App\Repository\SMTPConfigRepository;
use App\Service\EmailTransportService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;

class EmailTransportServiceTest extends TestCase
{
    private MailerInterface $mailer;
    private SMTPConfigRepository $smtpRepo;
    private ParameterBagInterface $params;
    private EmailTransportService $service;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $this->params = $this->createMock(ParameterBagInterface::class);

        $this->params->method('get')->willReturnCallback(function ($key) {
            return match ($key) {
                'app.email_subject' => 'Ihre Rückmeldung zu Ticket {{ticketId}}',
                'app.ticket_base_url' => 'https://tickets.example',
                'app.test_email' => 'test@example.com',
                'app.sender_email' => 'noreply@example.com',
                'app.sender_name' => 'Ticket-System',
                default => null,
            };
        });

        $this->smtpRepo->method('getConfig')->willReturn(null);

        $this->service = new EmailTransportService(
            $this->mailer,
            $this->smtpRepo,
            $this->params,
        );
    }

    public function testBuildEmailConfigWithoutDbConfig(): void
    {
        $config = $this->service->buildEmailConfig(false, null);

        $this->assertInstanceOf(EmailConfig::class, $config);
        $this->assertEquals('https://tickets.example', $config->ticketBaseUrl);
        $this->assertFalse($config->useCustomSMTP);
        $this->assertEquals('Ticket-System', $config->senderName);
        $this->assertEquals('test@example.com', (string) $config->testEmail);
    }

    public function testBuildEmailConfigUsesDbConfig(): void
    {
        $dbConfig = $this->createMock(\App\Entity\SMTPConfig::class);
        $dbConfig->method('getSenderEmail')->willReturn(\App\ValueObject\EmailAddress::fromString('dbsender@example.com'));
        $dbConfig->method('getSenderName')->willReturn('DB Sender');
        $dbConfig->method('getTicketBaseUrl')->willReturn('https://db.example');
        $dbConfig->method('getDSN')->willReturn('smtp://u:p@smtp.example:587?encryption=tls&verify_peer=0');

        $smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $smtpRepo->method('getConfig')->willReturn($dbConfig);

        $service = new EmailTransportService($this->mailer, $smtpRepo, $this->params);
        $config = $service->buildEmailConfig(false, null);

        $this->assertInstanceOf(EmailConfig::class, $config);
        $this->assertEquals('https://db.example', $config->ticketBaseUrl);
        $this->assertStringContainsString('smtp://', $config->smtpDSN);
        $this->assertTrue($config->useCustomSMTP);
        $this->assertEquals('DB Sender', $config->senderName);
    }

    public function testBuildEmailConfigWithCustomTestEmail(): void
    {
        $config = $this->service->buildEmailConfig(true, 'custom@example.com');

        $this->assertEquals('custom@example.com', (string) $config->testEmail);
    }

    public function testBuildEmailConfigIgnoresEmptyCustomTestEmail(): void
    {
        $config = $this->service->buildEmailConfig(true, '  ');

        $this->assertEquals('test@example.com', (string) $config->testEmail);
    }

    public function testSendEmailUsesHtmlWhenContentLooksLikeHtml(): void
    {
        $called = false;
        $this->mailer->expects($this->once())->method('send')->with($this->callback(function ($email) use (&$called) {
            $called = true;
            return method_exists($email, 'getHtmlBody') && strpos($email->getHtmlBody(), '<p>hi</p>') !== false;
        }));

        $config = new EmailConfig(
            subject: 'sub',
            ticketBaseUrl: 'https://example.com',
            senderEmail: 'sender@example.com',
            senderName: 'name',
            testEmail: 'test@example.com',
            useCustomSMTP: false,
        );

        $this->service->sendEmail('r@example.com', 'sub', '<html><p>hi</p></html>', $config);

        $this->assertTrue($called);
    }

    public function testSendEmailUsesTextWhenContentIsPlainText(): void
    {
        $capturedEmail = null;
        $this->mailer->expects($this->once())->method('send')->with($this->callback(function ($email) use (&$capturedEmail) {
            $capturedEmail = $email;
            return true;
        }));

        $config = new EmailConfig(
            subject: 'sub',
            ticketBaseUrl: 'https://example.com',
            senderEmail: 'sender@example.com',
            senderName: 'name',
            testEmail: 'test@example.com',
            useCustomSMTP: false,
        );

        $this->service->sendEmail('r@example.com', 'sub', 'Plain text content', $config);

        $this->assertNotNull($capturedEmail);
        $this->assertEquals('Plain text content', $capturedEmail->getTextBody());
        $this->assertNull($capturedEmail->getHtmlBody());
    }
}
