<?php

namespace App\Tests\Event;

use App\Event\User\UserImportCompletedEvent;
use PHPUnit\Framework\TestCase;

class UserImportCompletedEventTest extends TestCase
{
    public function testEventCreationAndProperties(): void
    {
        $successCount = 80;
        $errorCount = 5;
        $errors = ['Error 1', 'Error 2'];
        $filename = 'import.csv';
        $duration = 2.5;
        
        $event = new UserImportCompletedEvent(
            $successCount,
            $errorCount,
            $errors,
            $filename,
            $duration
        );
        
        $this->assertEquals($successCount, $event->successCount);
        $this->assertEquals($errorCount, $event->errorCount);
        $this->assertEquals($errors, $event->errors);
        $this->assertEquals($filename, $event->filename);
        $this->assertEquals($duration, $event->durationInSeconds);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }
    
    public function testGetTotalProcessed(): void
    {
        $event = new UserImportCompletedEvent(80, 5, [], 'test.csv', 1.0);
        
        $this->assertEquals(85, $event->getTotalProcessed());
    }
    
    public function testGetSuccessRate(): void
    {
        $event = new UserImportCompletedEvent(80, 20, [], 'test.csv', 1.0);
        
        $this->assertEquals(80.0, $event->getSuccessRate());
    }
    
    public function testGetSuccessRateWithZeroTotal(): void
    {
        $event = new UserImportCompletedEvent(0, 0, [], 'test.csv', 1.0);
        
        $this->assertEquals(0.0, $event->getSuccessRate());
    }
    
    public function testWasSuccessful(): void
    {
        $successfulEvent = new UserImportCompletedEvent(100, 0, [], 'test.csv', 1.0);
        $failedEvent = new UserImportCompletedEvent(80, 5, [], 'test.csv', 1.0);
        
        $this->assertTrue($successfulEvent->wasSuccessful());
        $this->assertFalse($failedEvent->wasSuccessful());
    }
}