<?php

namespace App\Tests\Dto;

use App\Dto\UnknownUsersResult;
use PHPUnit\Framework\TestCase;

class UnknownUsersResultTest extends TestCase
{
    public function testSuccessWithSingleUser(): void
    {
        $result = UnknownUsersResult::success(1);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->newUsersCount);
        $this->assertSame('1 neuer Benutzer wurde erfolgreich angelegt', $result->message);
        $this->assertSame('success', $result->flashType);
    }

    public function testSuccessWithMultipleUsers(): void
    {
        $result = UnknownUsersResult::success(5);

        $this->assertTrue($result->success);
        $this->assertSame(5, $result->newUsersCount);
        $this->assertStringContainsString('5', $result->message);
        $this->assertStringContainsString('neue Benutzer', $result->message);
        $this->assertStringContainsString('erfolgreich angelegt', $result->message);
        $this->assertSame('success', $result->flashType);
    }

    public function testSuccessWithZeroUsers(): void
    {
        $result = UnknownUsersResult::success(0);

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->newUsersCount);
        $this->assertSame('success', $result->flashType);
    }

    public function testNoUsersFound(): void
    {
        $result = UnknownUsersResult::noUsersFound();

        $this->assertFalse($result->success);
        $this->assertSame(0, $result->newUsersCount);
        $this->assertSame('Keine unbekannten Benutzer zu verarbeiten', $result->message);
        $this->assertSame('warning', $result->flashType);
    }

    public function testSingularVsPluralMessage(): void
    {
        $singular = UnknownUsersResult::success(1);
        $plural = UnknownUsersResult::success(2);

        $this->assertStringNotContainsString('Benutzer wurden', $singular->message);
        $this->assertStringContainsString('Benutzer wurde', $singular->message);
        $this->assertStringContainsString('Benutzer wurden', $plural->message);
    }
}
