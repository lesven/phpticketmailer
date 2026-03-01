<?php

namespace App\Tests\Event;

use App\Event\User\UserImportedEvent;
use App\ValueObject\EmailAddress;
use App\ValueObject\Username;
use PHPUnit\Framework\TestCase;

class UserImportedEventTest extends TestCase
{
    public function testEventCreationAndProperties(): void
    {
        $username = Username::fromString('anna_schmidt');
        $email = EmailAddress::fromString('anna@example.com');

        $event = new UserImportedEvent($username, $email, true);

        $this->assertSame($username, $event->username);
        $this->assertSame($email, $event->email);
        $this->assertTrue($event->excludedFromSurveys);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }

    public function testDefaultExcludedFromSurveysIsFalse(): void
    {
        $username = Username::fromString('bob_builder');
        $email = EmailAddress::fromString('bob@example.com');

        $event = new UserImportedEvent($username, $email);

        $this->assertFalse($event->excludedFromSurveys);
    }

    public function testExcludedFromSurveysExplicitlyFalse(): void
    {
        $username = Username::fromString('charlie_dev');
        $email = EmailAddress::fromString('charlie@example.com');

        $event = new UserImportedEvent($username, $email, false);

        $this->assertFalse($event->excludedFromSurveys);
    }

    public function testOccurredAtIsSetOnCreation(): void
    {
        $before = new \DateTimeImmutable();
        $event = new UserImportedEvent(
            Username::fromString('user_d'),
            EmailAddress::fromString('d@example.com')
        );
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->getOccurredAt());
        $this->assertLessThanOrEqual($after, $event->getOccurredAt());
    }
}
