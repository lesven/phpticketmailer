<?php
/**
 * EmailStatusTest.php
 * 
 * Unit-Tests für das EmailStatus Value Object.
 * Testet die Validierung und Factory-Methoden.
 */

namespace App\Tests\ValueObject;

use App\ValueObject\EmailStatus;
use PHPUnit\Framework\TestCase;

class EmailStatusTest extends TestCase
{
    public function testFromStringCreatesValidStatus(): void
    {
        $status = EmailStatus::fromString('Versendet');
        
        $this->assertEquals('Versendet', $status->getValue());
        $this->assertEquals('Versendet', (string) $status);
    }

    public function testThrowsExceptionForEmptyStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status darf nicht leer sein');
        
        EmailStatus::fromString('');
    }

    public function testThrowsExceptionForWhitespaceOnlyStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status darf nicht leer sein');
        
        EmailStatus::fromString('   ');
    }

    public function testThrowsExceptionForTooLongStatus(): void
    {
        $longStatus = str_repeat('A', 51);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status darf maximal 50 Zeichen haben');
        
        EmailStatus::fromString($longStatus);
    }

    public function testTrimsWhitespace(): void
    {
        $status = EmailStatus::fromString('  Versendet  ');
        
        $this->assertEquals('Versendet', $status->getValue());
    }

    public function testAlreadyProcessedFactoryMethod(): void
    {
        $date = new \DateTime('2025-08-26');
        $status = EmailStatus::alreadyProcessed($date);
        
        $this->assertEquals('Bereits verarbeitet am 26.08.2025', $status->getValue());
        $this->assertLessThanOrEqual(50, strlen($status->getValue()));
    }

    public function testDuplicateInCsvFactoryMethod(): void
    {
        $status = EmailStatus::duplicateInCsv();
        
        $this->assertEquals('Nicht versendet – Mehrfach in CSV', $status->getValue());
        $this->assertLessThanOrEqual(50, strlen($status->getValue()));
    }

    public function testExcludedFromSurveyFactoryMethod(): void
    {
        $status = EmailStatus::excludedFromSurvey();
        
        $this->assertEquals('Nicht versendet – Von Umfragen ausgeschlossen', $status->getValue());
        $this->assertLessThanOrEqual(50, strlen($status->getValue()));
    }

    public function testSentFactoryMethod(): void
    {
        $status = EmailStatus::sent();
        
        $this->assertEquals('Versendet', $status->getValue());
        $this->assertLessThanOrEqual(50, strlen($status->getValue()));
    }

    public function testErrorFactoryMethod(): void
    {
        $status = EmailStatus::error('Database connection failed');
        
        $this->assertEquals('Fehler: Database connection failed', $status->getValue());
        $this->assertLessThanOrEqual(50, strlen($status->getValue()));
    }

    public function testErrorFactoryMethodWithLongMessage(): void
    {
        $longMessage = str_repeat('A', 100);
        $status = EmailStatus::error($longMessage);
        
        $this->assertLessThanOrEqual(50, strlen($status->getValue()));
        $this->assertStringStartsWith('Fehler: ', $status->getValue());
        $this->assertStringEndsWith('...', $status->getValue());
    }

    public function testEquals(): void
    {
        $status1 = EmailStatus::fromString('Versendet');
        $status2 = EmailStatus::fromString('Versendet');
        $status3 = EmailStatus::fromString('Fehler');
        
        $this->assertTrue($status1->equals($status2));
        $this->assertFalse($status1->equals($status3));
    }

    public function testMaxLengthBoundary(): void
    {
        // Test mit genau 50 Zeichen
        $exactlyFiftyChars = str_repeat('A', 50);
        $status = EmailStatus::fromString($exactlyFiftyChars);
        
        $this->assertEquals(50, strlen($status->getValue()));
        $this->assertEquals($exactlyFiftyChars, $status->getValue());
    }

    public function testIsSentReturnsTrueForSentStatus(): void
    {
        $status = EmailStatus::sent();
        
        $this->assertTrue($status->isSent());
        $this->assertFalse($status->isError());
        $this->assertFalse($status->isAlreadyProcessed());
        $this->assertFalse($status->isDuplicate());
        $this->assertFalse($status->isExcludedFromSurvey());
    }

    public function testIsErrorReturnsTrueForErrorStatus(): void
    {
        $status = EmailStatus::error('Test error');
        
        $this->assertFalse($status->isSent());
        $this->assertTrue($status->isError());
        $this->assertFalse($status->isAlreadyProcessed());
        $this->assertFalse($status->isDuplicate());
        $this->assertFalse($status->isExcludedFromSurvey());
    }

    public function testIsAlreadyProcessedReturnsTrueForAlreadyProcessedStatus(): void
    {
        $date = new \DateTime('2025-08-26');
        $status = EmailStatus::alreadyProcessed($date);
        
        $this->assertFalse($status->isSent());
        $this->assertFalse($status->isError());
        $this->assertTrue($status->isAlreadyProcessed());
        $this->assertFalse($status->isDuplicate());
        $this->assertFalse($status->isExcludedFromSurvey());
    }

    public function testIsDuplicateReturnsTrueForDuplicateStatus(): void
    {
        $status = EmailStatus::duplicateInCsv();
        
        $this->assertFalse($status->isSent());
        $this->assertFalse($status->isError());
        $this->assertFalse($status->isAlreadyProcessed());
        $this->assertTrue($status->isDuplicate());
        $this->assertFalse($status->isExcludedFromSurvey());
    }

    public function testIsExcludedFromSurveyReturnsTrueForExcludedStatus(): void
    {
        $status = EmailStatus::excludedFromSurvey();
        
        $this->assertFalse($status->isSent());
        $this->assertFalse($status->isError());
        $this->assertFalse($status->isAlreadyProcessed());
        $this->assertFalse($status->isDuplicate());
        $this->assertTrue($status->isExcludedFromSurvey());
    }

    public function testStatusTypeMethodsWithCustomStatus(): void
    {
        $customStatus = EmailStatus::fromString('Custom status message');
        
        $this->assertFalse($customStatus->isSent());
        $this->assertFalse($customStatus->isError());
        $this->assertFalse($customStatus->isAlreadyProcessed());
        $this->assertFalse($customStatus->isDuplicate());
        $this->assertFalse($customStatus->isExcludedFromSurvey());
    }
}
