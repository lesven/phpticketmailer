<?php

namespace App\Tests\Service\Email;

use App\Dto\EmailConfig;
use App\Service\Email\EmailSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

class EmailSenderTest extends TestCase
{
    public function testSendUsesHtmlWhenContentContainsHtmlTags(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($this->callback(function ($email) {
            return method_exists($email, 'getHtmlBody') && strpos($email->getHtmlBody(), '<p>hi</p>') !== false;
        }));

        $sender = new EmailSender($mailer);
        $config = new EmailConfig(
            subject: 'sub',
            ticketBaseUrl: 'https://example.com',
            senderEmail: 'sender@example.com',
            senderName: 'Sender',
            testEmail: 'test@example.com',
            useCustomSMTP: false,
        );

        $sender->send('recipient@example.com', 'sub', '<html><p>hi</p></html>', $config);
    }

    public function testSendUsesTextWhenContentIsPlainText(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($this->callback(function ($email) {
            return method_exists($email, 'getTextBody') && strpos($email->getTextBody(), 'plain text') !== false;
        }));

        $sender = new EmailSender($mailer);
        $config = new EmailConfig(
            subject: 'sub',
            ticketBaseUrl: 'https://example.com',
            senderEmail: 'sender@example.com',
            senderName: 'Sender',
            testEmail: 'test@example.com',
            useCustomSMTP: false,
        );

        $sender->send('recipient@example.com', 'sub', 'plain text content', $config);
    }

    public function testSendUsesDefaultMailerWhenNotCustomSmtp(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $sender = new EmailSender($mailer);
        $config = new EmailConfig(
            subject: 'sub',
            ticketBaseUrl: 'https://example.com',
            senderEmail: 'sender@example.com',
            senderName: 'Sender',
            testEmail: 'test@example.com',
            useCustomSMTP: false,
        );

        $sender->send('r@example.com', 'sub', 'body', $config);
    }
}
