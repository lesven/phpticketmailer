<?php

namespace App\Tests\Dto;

use App\Dto\UserImportResult;
use PHPUnit\Framework\TestCase;

class UserImportResultTest extends TestCase
{
    public function testSuccessFactoryWithOnlyCreatedCount(): void
    {
        $result = UserImportResult::success(5);

        $this->assertTrue($result->success);
        $this->assertSame(5, $result->createdCount);
        $this->assertSame(0, $result->skippedCount);
        $this->assertSame([], $result->errors);
        $this->assertStringContainsString('5', $result->message);
        $this->assertStringContainsString('erfolgreich importiert', $result->message);
    }

    public function testSuccessFactoryWithSkipped(): void
    {
        $result = UserImportResult::success(10, 3);

        $this->assertSame(10, $result->createdCount);
        $this->assertSame(3, $result->skippedCount);
        $this->assertStringContainsString('10', $result->message);
        $this->assertStringContainsString('3', $result->message);
        $this->assertStringContainsString('übersprungen', $result->message);
    }

    public function testSuccessFactoryWithErrors(): void
    {
        $errors = ['User A: invalid email', 'User B: duplicate'];
        $result = UserImportResult::success(8, 0, $errors);

        $this->assertSame($errors, $result->errors);
        $this->assertStringContainsString('2', $result->message);
        $this->assertStringContainsString('Fehler', $result->message);
    }

    public function testSuccessFactoryWithSkippedAndErrors(): void
    {
        $errors = ['Some error'];
        $result = UserImportResult::success(7, 2, $errors);

        $this->assertStringContainsString('7', $result->message);
        $this->assertStringContainsString('2', $result->message);
        $this->assertStringContainsString('1', $result->message);
    }

    public function testErrorFactory(): void
    {
        $result = UserImportResult::error('File not found');

        $this->assertFalse($result->success);
        $this->assertSame(0, $result->createdCount);
        $this->assertSame(0, $result->skippedCount);
        $this->assertSame([], $result->errors);
        $this->assertSame('File not found', $result->message);
    }

    public function testHasErrorsReturnsTrueWhenErrorsPresent(): void
    {
        $result = UserImportResult::success(5, 0, ['error 1']);

        $this->assertTrue($result->hasErrors());
    }

    public function testHasErrorsReturnsFalseWhenNoErrors(): void
    {
        $result = UserImportResult::success(5);

        $this->assertFalse($result->hasErrors());
    }

    public function testGetFlashTypeForSuccessWithoutErrors(): void
    {
        $result = UserImportResult::success(3);

        $this->assertSame('success', $result->getFlashType());
    }

    public function testGetFlashTypeForSuccessWithErrors(): void
    {
        $result = UserImportResult::success(3, 0, ['partial error']);

        $this->assertSame('warning', $result->getFlashType());
    }

    public function testGetFlashTypeForError(): void
    {
        $result = UserImportResult::error('Critical failure');

        $this->assertSame('error', $result->getFlashType());
    }
}
