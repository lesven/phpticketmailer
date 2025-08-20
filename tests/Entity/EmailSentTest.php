<?php

use PHPUnit\Framework\TestCase;
use App\Entity\EmailSent;

final class EmailSentTest extends TestCase
{
    public function testGetSetBasicFieldsAndTimestampFormatting(): void
    {
        $e = new EmailSent();

        $this->assertNull($e->getId());
        $this->assertNull($e->getTicketId());
        $this->assertNull($e->getUsername());
        $this->assertNull($e->getEmail());
        $this->assertNull($e->getSubject());
        $this->assertNull($e->getStatus());
        $this->assertNull($e->getTimestamp());
        $this->assertNull($e->getTestMode());
        $this->assertNull($e->getTicketName());

        $this->assertSame('', $e->getFormattedTimestamp());

        $e->setTicketId('T-123')
          ->setUsername('alice')
          ->setEmail('a@example.com')
          ->setSubject('Hello')
          ->setStatus('sent')
          ->setTestMode(true)
          ->setTicketName('Ticket A');

        $now = new \DateTimeImmutable('2025-08-20 12:34:56');
        $e->setTimestamp($now);

        $this->assertSame('T-123', $e->getTicketId());
        $this->assertSame('alice', $e->getUsername());
        $this->assertSame('a@example.com', $e->getEmail());
        $this->assertSame('Hello', $e->getSubject());
        $this->assertSame('sent', $e->getStatus());
        $this->assertSame($now, $e->getTimestamp());
        $this->assertTrue($e->getTestMode());
        $this->assertSame('Ticket A', $e->getTicketName());

        $this->assertSame('2025-08-20 12:34:56', $e->getFormattedTimestamp());
    }
}
