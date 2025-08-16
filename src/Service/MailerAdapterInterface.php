<?php
namespace App\Service;

use Symfony\Component\Mime\Email;

interface MailerAdapterInterface
{
    public function send(Email $email): void;
}
