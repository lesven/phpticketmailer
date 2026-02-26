<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Exception\ValidationException;
use App\Exception\TicketMailerException;

final class ValidationExceptionTest extends TestCase
{
    public function test_invalidEmailForUser_creates_correct_exception(): void
    {
        $ex = ValidationException::invalidEmailForUser('john.doe', 'Format ungültig');

        $this->assertInstanceOf(ValidationException::class, $ex);
        $this->assertInstanceOf(TicketMailerException::class, $ex);
        $this->assertStringContainsString('john.doe', $ex->getMessage());
        $this->assertStringContainsString('Format ungültig', $ex->getMessage());
        $this->assertSame('validation_error', $ex->getContext()['type']);
        $this->assertSame('email', $ex->getContext()['field']);
        $this->assertSame('john.doe', $ex->getContext()['username']);
    }

    public function test_invalidField_creates_correct_exception(): void
    {
        $ex = ValidationException::invalidField('ticketId', 'Darf nicht leer sein');

        $this->assertInstanceOf(ValidationException::class, $ex);
        $this->assertStringContainsString('ticketId', $ex->getMessage());
        $this->assertStringContainsString('Darf nicht leer sein', $ex->getMessage());
        $this->assertSame('validation_error', $ex->getContext()['type']);
        $this->assertSame('ticketId', $ex->getContext()['field']);
    }

    public function test_getUserMessage_returns_message(): void
    {
        $ex = ValidationException::invalidEmailForUser('user1', 'details');

        $this->assertNotEmpty($ex->getUserMessage());
        $this->assertSame($ex->getMessage(), $ex->getUserMessage());
    }
}
