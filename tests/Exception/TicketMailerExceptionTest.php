<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Exception\TicketMailerException;

final class TicketMailerExceptionTest extends TestCase
{
    public function test_basisklasse_stellt_kontext_und_debuginfo_bereit(): void
    {
        $previous = new \Exception('inner');

        $exception = new class('Fehlertext', 123, $previous, ['meta' => 'value']) extends TicketMailerException {};

        // Basis-Assertions
        $this->assertSame('Fehlertext', $exception->getMessage());
        $this->assertSame(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());

        // Kontext
        $this->assertSame(['meta' => 'value'], $exception->getContext());

        // Benutzerfreundliche Nachricht (Default = message)
        $this->assertSame('Fehlertext', $exception->getUserMessage());

        // Debug-Info
        $debug = $exception->getDebugInfo();
        $this->assertArrayHasKey('exception', $debug);
        $this->assertArrayHasKey('message', $debug);
        $this->assertArrayHasKey('context', $debug);
        $this->assertSame('Fehlertext', $debug['message']);
        $this->assertSame(['meta' => 'value'], $debug['context']);
    }
}
