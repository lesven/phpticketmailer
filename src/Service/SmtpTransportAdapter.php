<?php
namespace App\Service;

use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class SmtpTransportAdapter implements MailerAdapterInterface
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function send(Email $email): void
    {
        $this->transport->send($email);
    }
}
