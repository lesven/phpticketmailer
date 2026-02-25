<?php

namespace App\Service;

use App\Dto\EmailConfig;
use App\Repository\SMTPConfigRepository;
use App\ValueObject\EmailAddress;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Verantwortlich für SMTP-Konfiguration und den tatsächlichen E-Mail-Versand.
 *
 * Kapselt Transport-Logik (Standard-Mailer vs. Custom-SMTP) und
 * baut die typisierte EmailConfig aus DB oder App-Parametern.
 */
class EmailTransportService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SMTPConfigRepository $smtpConfigRepository,
        private readonly ParameterBagInterface $params,
    ) {
    }

    /**
     * Erstellt die typisierte E-Mail-Konfiguration aus DB oder Parametern.
     */
    public function buildEmailConfig(bool $testMode, ?string $customTestEmail): EmailConfig
    {
        $config = $this->smtpConfigRepository->getConfig();

        $subject = $this->params->get('app.email_subject') ?? 'Feedback zu Ticket {{ticketId}}';
        $ticketBaseUrl = $this->params->get('app.ticket_base_url') ?? 'https://www.ticket.de';
        $testEmail = $this->params->get('app.test_email') ?? 'test@example.com';

        if ($testMode && $customTestEmail !== null && !empty(trim($customTestEmail))) {
            $testEmail = trim($customTestEmail);
        }

        if ($config) {
            return new EmailConfig(
                subject: $subject,
                ticketBaseUrl: $config->getTicketBaseUrl(),
                senderEmail: $config->getSenderEmail(),
                senderName: $config->getSenderName(),
                testEmail: $testEmail,
                useCustomSMTP: true,
                smtpDSN: $config->getDSN(),
            );
        }

        return new EmailConfig(
            subject: $subject,
            ticketBaseUrl: $ticketBaseUrl,
            senderEmail: $this->params->get('app.sender_email') ?? 'noreply@example.com',
            senderName: $this->params->get('app.sender_name') ?? 'Ticket-System',
            testEmail: $testEmail,
            useCustomSMTP: false,
        );
    }

    /**
     * Sendet eine E-Mail über den konfigurierten Transport.
     */
    public function sendEmail(
        EmailAddress|string $recipient,
        string $subject,
        string $content,
        EmailConfig $config,
    ): void {
        $email = (new Email())
            ->from(new Address((string) $config->senderEmail, $config->senderName))
            ->to((string) $recipient)
            ->subject($subject);

        if (strpos($content, '<html') !== false || strpos($content, '<p>') !== false || strpos($content, '<div>') !== false) {
            $email->html($content);
        } else {
            $email->text($content);
        }

        if ($config->useCustomSMTP) {
            $transport = Transport::fromDsn($config->smtpDSN);
            $transport->send($email);
        } else {
            $this->mailer->send($email);
        }
    }
}
