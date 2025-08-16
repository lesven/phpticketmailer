<?php
namespace App\Service;

use Symfony\Component\Mailer\Transport;

class SymfonyMailTransportFactory implements MailTransportFactoryInterface
{
    public function createFromDsn(string $dsn): MailerAdapterInterface
    {
        $transport = Transport::fromDsn($dsn);
        return new SmtpTransportAdapter($transport);
    }
}
