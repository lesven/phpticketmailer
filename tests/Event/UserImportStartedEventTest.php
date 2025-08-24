<?php

namespace App\Tests\Event;

use App\Event\User\UserImportStartedEvent;
use PHPUnit\Framework\TestCase;

class UserImportStartedEventTest extends TestCase
{
    public function testEventCreationAndProperties(): void
    {
        $totalRows = 100;
        $filename = 'users.csv';
        $clearExisting = true;
        
        $event = new UserImportStartedEvent($totalRows, $filename, $clearExisting);
        
        $this->assertEquals($totalRows, $event->totalRows);
        $this->assertEquals($filename, $event->filename);
        $this->assertEquals($clearExisting, $event->clearExisting);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }
    
    public function testEventWithDefaults(): void
    {
        $event = new UserImportStartedEvent(50, 'test.csv');
        
        $this->assertEquals(50, $event->totalRows);
        $this->assertEquals('test.csv', $event->filename);
        $this->assertFalse($event->clearExisting);
    }
}