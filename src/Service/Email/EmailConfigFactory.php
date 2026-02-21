<?php

namespace App\Service\Email;

use App\Dto\EmailConfig;
use App\Repository\SMTPConfigRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Erstellt die typisierte E-Mail-Konfiguration aus DB oder App-Parametern.
 *
 * Kapselt die Auflösung der Versandkonfiguration (Absender, SMTP, Subject usw.)
 * als eigenständigen Service, der unabhängig vom Versandprozess testbar ist.
 */
class EmailConfigFactory
{
    public function __construct(
        private readonly SMTPConfigRepository $smtpConfigRepository,
        private readonly ParameterBagInterface $params,
    ) {
    }

    public function create(bool $testMode = false, ?string $customTestEmail = null): EmailConfig
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
}
