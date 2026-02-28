<?php

namespace App\Tests\Event;

use App\Event\Email\EmailSkippedEvent;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use PHPUnit\Framework\TestCase;

class EmailSkippedEventTest extends TestCase
{
    public function testEventCreationAndProperties(): void
    {
        $ticketData = TicketData::fromStrings('T-99001', 'max_muster', 'Login Bug');
        $email = EmailAddress::fromString('max@example.com');
        $status = EmailStatus::duplicateInCsv();

        $event = new EmailSkippedEvent($ticketData, $email, $status, true);

        $this->assertSame($ticketData, $event->ticketData);
        $this->assertSame($email, $event->email);
        $this->assertSame($status, $event->status);
        $this->assertTrue($event->testMode);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }

    public function testDefaultTestModeIsFalse(): void
    {
        $ticketData = TicketData::fromStrings('T-99002', 'user_a');
        $status = EmailStatus::alreadyProcessed(new \DateTimeImmutable('2024-01-01'));

        $event = new EmailSkippedEvent($ticketData, null, $status);

        $this->assertFalse($event->testMode);
    }

    public function testNullableEmail(): void
    {
        $ticketData = TicketData::fromStrings('T-99003', 'ghost_user');
        $status = EmailStatus::error('no email found');

        $event = new EmailSkippedEvent($ticketData, null, $status);

        $this->assertNull($event->email);
    }

    public function testConvenienceGettersDelegate(): void
    {
        $ticketData = TicketData::fromStrings('T-99004', 'jane_doe', 'Timeout Error');
        $email = EmailAddress::fromString('jane@example.com');
        $status = EmailStatus::duplicateInCsv();

        $event = new EmailSkippedEvent($ticketData, $email, $status);

        $this->assertEquals($ticketData->ticketId, $event->getTicketId());
        $this->assertEquals($ticketData->username, $event->getUsername());
        $this->assertEquals($ticketData->ticketName, $event->getTicketName());
    }

    public function testConvenienceGetterWithNullTicketName(): void
    {
        $ticketData = TicketData::fromStrings('T-99005', 'anon_user');
        $status = EmailStatus::duplicateInCsv();

        $event = new EmailSkippedEvent($ticketData, null, $status);

        $this->assertNull($event->getTicketName());
    }

    public function testOccurredAtIsSetOnCreation(): void
    {
        $before = new \DateTimeImmutable();
        $event = new EmailSkippedEvent(
            TicketData::fromStrings('T-99006', 'user_b'),
            null,
            EmailStatus::duplicateInCsv()
        );
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->getOccurredAt());
        $this->assertLessThanOrEqual($after, $event->getOccurredAt());
    }
}
