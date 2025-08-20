<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Exception\EmailSendingException;

final class EmailSendingExceptionTest extends TestCase
{
    public function test_smtpConfigurationError_and_usermessage(): void
    {
        $ex = EmailSendingException::smtpConfigurationError('Port fehlt');

        $this->assertInstanceOf(EmailSendingException::class, $ex);
        $this->assertStringContainsString('SMTP-Konfigurationsfehler', $ex->getMessage());
        $this->assertSame('Die E-Mail-Konfiguration ist fehlerhaft. Bitte überprüfen Sie die SMTP-Einstellungen.', $ex->getUserMessage());
    }

    public function test_connectionError_and_previous(): void
    {
        $prev = new \Exception('net');
        $ex = EmailSendingException::connectionError('smtp.example.org', 587, $prev);

        $this->assertStringContainsString('Verbindung zum SMTP-Server smtp.example.org:587 fehlgeschlagen', $ex->getMessage());
        $this->assertSame($prev, $ex->getPrevious());
        $this->assertSame('Der E-Mail-Server ist nicht erreichbar. Bitte versuchen Sie es später erneut.', $ex->getUserMessage());
        $this->assertSame('smtp.example.org', $ex->getContext()['smtp_host']);
        $this->assertSame(587, $ex->getContext()['smtp_port']);
    }

    public function test_authenticationError_and_usermessage(): void
    {
        $prev = new \Exception('auth');
        $ex = EmailSendingException::authenticationError('bob', $prev);

        $this->assertStringContainsString("SMTP-Authentifizierung für Benutzer 'bob' fehlgeschlagen", $ex->getMessage());
        $this->assertSame($prev, $ex->getPrevious());
        $this->assertSame('Die Anmeldung am E-Mail-Server ist fehlgeschlagen. Bitte überprüfen Sie Benutzername und Passwort.', $ex->getUserMessage());
        $this->assertSame('bob', $ex->getContext()['smtp_username']);
    }

    public function test_invalidEmailAddress_and_templateNotFound_usermessages(): void
    {
        $ex1 = EmailSendingException::invalidEmailAddress('foo@bar');
        $this->assertSame('Eine oder mehrere E-Mail-Adressen sind ungültig.', $ex1->getUserMessage());

        $ex2 = EmailSendingException::templateNotFound('/templates/missing.html.twig');
        $this->assertSame('Das E-Mail-Template wurde nicht gefunden.', $ex2->getUserMessage());
    }
}
