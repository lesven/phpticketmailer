<?php

namespace App\Tests\Dto;

use App\Dto\CsvProcessingResult;
use App\ValueObject\TicketData;
use App\ValueObject\UnknownUserWithTicket;
use PHPUnit\Framework\TestCase;

class CsvProcessingResultTest extends TestCase
{
    public function testDefaultConstructorCreatesEmptyArrays(): void
    {
        $result = new CsvProcessingResult();

        $this->assertSame([], $result->validTickets);
        $this->assertSame([], $result->invalidRows);
        $this->assertSame([], $result->unknownUsers);
    }

    public function testConstructorWithValidTickets(): void
    {
        $ticket1 = TicketData::fromStrings('T-001', 'user_a', 'Bug Fix');
        $ticket2 = TicketData::fromStrings('T-002', 'user_b', 'Feature Request');

        $result = new CsvProcessingResult(validTickets: [$ticket1, $ticket2]);

        $this->assertCount(2, $result->validTickets);
        $this->assertSame($ticket1, $result->validTickets[0]);
        $this->assertSame($ticket2, $result->validTickets[1]);
        $this->assertSame([], $result->invalidRows);
        $this->assertSame([], $result->unknownUsers);
    }

    public function testConstructorWithInvalidRows(): void
    {
        $invalidRow = ['rowNumber' => 3, 'data' => ['ticketId' => '', 'username' => ''], 'error' => 'Missing ticketId'];

        $result = new CsvProcessingResult(invalidRows: [$invalidRow]);

        $this->assertSame([], $result->validTickets);
        $this->assertCount(1, $result->invalidRows);
        $this->assertSame($invalidRow, $result->invalidRows[0]);
    }

    public function testConstructorWithUnknownUsers(): void
    {
        $ticketData = TicketData::fromStrings('T-999', 'unknown_user', 'Mystery Ticket');
        $unknown = UnknownUserWithTicket::fromTicketData($ticketData);

        $result = new CsvProcessingResult(unknownUsers: [$unknown]);

        $this->assertSame([], $result->validTickets);
        $this->assertSame([], $result->invalidRows);
        $this->assertCount(1, $result->unknownUsers);
        $this->assertSame($unknown, $result->unknownUsers[0]);
    }

    public function testConstructorWithAllParameters(): void
    {
        $ticket = TicketData::fromStrings('T-100', 'user_ok');
        $invalidRow = ['rowNumber' => 5, 'data' => [], 'error' => 'Invalid email'];
        $ghostData = TicketData::fromStrings('T-200', 'ghost', 'Ghost Ticket');
        $unknown = UnknownUserWithTicket::fromTicketData($ghostData);

        $result = new CsvProcessingResult(
            validTickets: [$ticket],
            invalidRows: [$invalidRow],
            unknownUsers: [$unknown]
        );

        $this->assertCount(1, $result->validTickets);
        $this->assertCount(1, $result->invalidRows);
        $this->assertCount(1, $result->unknownUsers);
    }
}
