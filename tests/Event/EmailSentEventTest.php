<?php

namespace App\Tests\Event;

use App\Event\Email\EmailSentEvent;
use App\ValueObject\TicketId;
use App\ValueObject\Username;
use App\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

class EmailSentEventTest extends TestCase
{
    public function testEventCreationAndProperties(): void
    {
        $ticketId = TicketId::fromString('T-12345');
        $username = Username::fromString('john_doe');
        $email = EmailAddress::fromString('john@example.com');
        $subject = 'Your Ticket Update';
        $testMode = true;
        $ticketName = 'System Issue';
        
        $event = new EmailSentEvent(
            $ticketId,
            $username,
            $email,
            $subject,
            $testMode,
            $ticketName
        );
        
        $this->assertEquals($ticketId, $event->ticketId);
        $this->assertEquals($username, $event->username);
        $this->assertEquals($email, $event->email);
        $this->assertEquals($subject, $event->subject);
        $this->assertEquals($testMode, $event->testMode);
        $this->assertEquals($ticketName, $event->ticketName);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }
    
    public function testEventWithDefaults(): void
    {
        $ticketId = TicketId::fromString('T-67890');
        $username = Username::fromString('jane_doe');
        $email = EmailAddress::fromString('jane@example.com');
        $subject = 'Ticket Created';
        
        $event = new EmailSentEvent($ticketId, $username, $email, $subject);
        
        $this->assertFalse($event->testMode);
        $this->assertNull($event->ticketName);
    }
}