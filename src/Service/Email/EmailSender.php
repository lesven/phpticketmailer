<?php

namespace App\Service\Email;

use App\Dto\EmailConfig;
use App\ValueObject\EmailAddress;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

/**
 * Infrastruktur-Service für den tatsächlichen E-Mail-Versand.
 *
 * Kapselt die Symfony-Mailer-Logik und die Entscheidung zwischen
 * Default-Transport und benutzerdefiniertem SMTP-DSN.
 */
class EmailSender
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    /**
     * Versendet eine E-Mail über den konfigurierten Transport.
     */
    public function send(
        EmailAddress|string $recipient,
        string $subject,
        string $content,
        EmailConfig $config
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
