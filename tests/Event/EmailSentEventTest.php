<?php

namespace App\Tests\Event;

use App\Event\Email\EmailSentEvent;
use App\ValueObject\TicketData;
use App\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

class EmailSentEventTest extends TestCase
{
    public function testEventCreationAndProperties(): void
    {
        $ticketData = TicketData::fromStrings('T-12345', 'john_doe', 'System Issue');
        $email = EmailAddress::fromString('john@example.com');
        $subject = 'Your Ticket Update';
        $testMode = true;
        
        $event = new EmailSentEvent(
            $ticketData,
            $email,
            $subject,
            $testMode
        );
        
        $this->assertEquals($ticketData, $event->ticketData);
        $this->assertEquals($email, $event->email);
        $this->assertEquals($subject, $event->subject);
        $this->assertEquals($testMode, $event->testMode);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
        
        // Test backward compatibility getters
        $this->assertEquals($ticketData->ticketId, $event->getTicketId());
        $this->assertEquals($ticketData->username, $event->getUsername());
        $this->assertEquals($ticketData->ticketName, $event->getTicketName());
    }
    
    public function testEventWithDefaults(): void
    {
        $ticketData = TicketData::fromStrings('T-67890', 'jane_doe');
        $email = EmailAddress::fromString('jane@example.com');
        $subject = 'Ticket Created';
        
        $event = new EmailSentEvent($ticketData, $email, $subject);
        
        $this->assertFalse($event->testMode);
        $this->assertNull($event->getTicketName());
    }
}