<?php

use PHPUnit\Framework\TestCase;
use App\Entity\SMTPConfig;

final class SMTPConfigTest extends TestCase
{
    public function testGetSetAndDSNGeneration(): void
    {
        $c = new SMTPConfig();

        $c->setHost('smtp.example.local');
        $c->setPort(587);
        $c->setUsername('user');
        $c->setPassword('p@ss');
        $c->setUseTLS(true);
        $c->setVerifySSL(false);
        $c->setSenderEmail('noreply@example.local');
        $c->setSenderName('TicketMailer');
        $c->setTicketBaseUrl('https://tickets.example.local');

        $this->assertSame('smtp.example.local', $c->getHost());
        $this->assertSame(587, $c->getPort());
        $this->assertSame('user', $c->getUsername());
        $this->assertSame('p@ss', $c->getPassword());
        $this->assertTrue($c->isUseTLS());
        $this->assertFalse($c->getVerifySSL());
        $this->assertSame('noreply@example.local', $c->getSenderEmail());
        $this->assertSame('TicketMailer', $c->getSenderName());
        $this->assertSame('https://tickets.example.local', $c->getTicketBaseUrl());

        $dsn = $c->getDSN();
        // username and password should be urlencoded
        $this->assertStringContainsString('smtp://', $dsn);
        $this->assertStringContainsString(urlencode('user') . ':' . urlencode('p@ss') . '@', $dsn);
        $this->assertStringContainsString('smtp.example.local:587', $dsn);
        $this->assertStringContainsString('encryption=tls', $dsn);
        $this->assertStringContainsString('verify_peer=0', $dsn);
    }

    public function testDsnWithoutAuthAndWithoutParams(): void
    {
        $c = new SMTPConfig();
        $c->setHost('localhost');
        $c->setPort(25);
        $c->setUseTLS(false);
        $c->setVerifySSL(true);

        $dsn = $c->getDSN();
        $this->assertSame('smtp://localhost:25', $dsn);
    }
}
