<?php

namespace App\Tests\Event;

use App\Event\Email\BulkEmailCompletedEvent;
use PHPUnit\Framework\TestCase;

class BulkEmailCompletedEventTest extends TestCase
{
    public function testEventCreationAndProperties(): void
    {
        $event = new BulkEmailCompletedEvent(
            totalEmails: 20,
            sentCount: 15,
            failedCount: 2,
            skippedCount: 3,
            testMode: true,
            durationInSeconds: 4.75
        );

        $this->assertSame(20, $event->totalEmails);
        $this->assertSame(15, $event->sentCount);
        $this->assertSame(2, $event->failedCount);
        $this->assertSame(3, $event->skippedCount);
        $this->assertTrue($event->testMode);
        $this->assertEqualsWithDelta(4.75, $event->durationInSeconds, 0.001);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }

    public function testGetSuccessRateWithNormalValues(): void
    {
        $event = new BulkEmailCompletedEvent(20, 10, 5, 5, false, 1.0);

        $this->assertEqualsWithDelta(50.0, $event->getSuccessRate(), 0.001);
    }

    public function testGetSuccessRateWhenAllSent(): void
    {
        $event = new BulkEmailCompletedEvent(10, 10, 0, 0, false, 0.5);

        $this->assertEqualsWithDelta(100.0, $event->getSuccessRate(), 0.001);
    }

    public function testGetSuccessRateWhenNoneSent(): void
    {
        $event = new BulkEmailCompletedEvent(10, 0, 10, 0, false, 0.1);

        $this->assertEqualsWithDelta(0.0, $event->getSuccessRate(), 0.001);
    }

    public function testGetSuccessRateWithZeroTotal(): void
    {
        $event = new BulkEmailCompletedEvent(0, 0, 0, 0, false, 0.0);

        $this->assertEqualsWithDelta(0.0, $event->getSuccessRate(), 0.001);
    }

    public function testWasSuccessfulWhenNoFailed(): void
    {
        $event = new BulkEmailCompletedEvent(5, 5, 0, 0, false, 1.0);

        $this->assertTrue($event->wasSuccessful());
    }

    public function testWasSuccessfulWhenHasFailed(): void
    {
        $event = new BulkEmailCompletedEvent(10, 8, 2, 0, false, 1.0);

        $this->assertFalse($event->wasSuccessful());
    }

    public function testWasSuccessfulWhenAllFailed(): void
    {
        $event = new BulkEmailCompletedEvent(5, 0, 5, 0, false, 1.0);

        $this->assertFalse($event->wasSuccessful());
    }

    public function testTestModeDefault(): void
    {
        $event = new BulkEmailCompletedEvent(3, 3, 0, 0, false, 0.2);

        $this->assertFalse($event->testMode);
    }

    public function testOccurredAtIsSetOnCreation(): void
    {
        $before = new \DateTimeImmutable();
        $event = new BulkEmailCompletedEvent(1, 1, 0, 0, false, 0.1);
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->getOccurredAt());
        $this->assertLessThanOrEqual($after, $event->getOccurredAt());
    }
}
