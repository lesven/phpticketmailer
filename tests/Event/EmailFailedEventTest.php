<?php

namespace App\Tests\Event;

use App\Event\Email\EmailFailedEvent;
use App\ValueObject\EmailAddress;
use App\ValueObject\TicketData;
use PHPUnit\Framework\TestCase;

class EmailFailedEventTest extends TestCase
{
    public function testEventCreationAndProperties(): void
    {
        $ticketData = TicketData::fromStrings('T-88001', 'john_doe', 'SMTP Failure');
        $email = EmailAddress::fromString('john@example.com');
        $subject = 'Your Ticket: T-88001';
        $errorMessage = 'Connection refused';

        $event = new EmailFailedEvent($ticketData, $email, $subject, $errorMessage, true);

        $this->assertSame($ticketData, $event->ticketData);
        $this->assertSame($email, $event->email);
        $this->assertSame($subject, $event->subject);
        $this->assertSame($errorMessage, $event->errorMessage);
        $this->assertTrue($event->testMode);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }

    public function testDefaultTestModeIsFalse(): void
    {
        $ticketData = TicketData::fromStrings('T-88002', 'user_x');
        $email = EmailAddress::fromString('user@example.com');

        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'Timeout');

        $this->assertFalse($event->testMode);
    }

    public function testConvenienceGetters(): void
    {
        $ticketData = TicketData::fromStrings('T-88003', 'alice', 'Payment Bug');
        $email = EmailAddress::fromString('alice@example.com');

        $event = new EmailFailedEvent($ticketData, $email, 'Re: T-88003', 'DNS error');

        $this->assertEquals($ticketData->ticketId, $event->getTicketId());
        $this->assertEquals($ticketData->username, $event->getUsername());
        $this->assertEquals($ticketData->ticketName, $event->getTicketName());
    }

    public function testConvenienceGetterNullTicketName(): void
    {
        $ticketData = TicketData::fromStrings('T-88004', 'bob');
        $email = EmailAddress::fromString('bob@example.com');

        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'Error msg');

        $this->assertNull($event->getTicketName());
    }

    public function testOccurredAtIsSetOnCreation(): void
    {
        $before = new \DateTimeImmutable();
        $event = new EmailFailedEvent(
            TicketData::fromStrings('T-88005', 'user_c'),
            EmailAddress::fromString('c@example.com'),
            'Subj',
            'err'
        );
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->getOccurredAt());
        $this->assertLessThanOrEqual($after, $event->getOccurredAt());
    }
}
