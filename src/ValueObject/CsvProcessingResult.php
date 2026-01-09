<?php

namespace App\ValueObject;

use App\ValueObject\TicketData;

final class CsvProcessingResult
{
    /** @var TicketData[] */
    private array $validTickets;

    /** @var array[] */
    private array $invalidRows;

    /** @var array */
    private array $unknownUsers;

    /**
     * @param TicketData[] $validTickets
     * @param array[] $invalidRows
     * @param array $unknownUsers
     */
    public function __construct(array $validTickets, array $invalidRows, array $unknownUsers)
    {
        $this->validTickets = $validTickets;
        $this->invalidRows = $invalidRows;
        $this->unknownUsers = $unknownUsers;
    }

    /** @return TicketData[] */
    public function getValidTickets(): array
    {
        return $this->validTickets;
    }

    /** @return array[] */
    public function getInvalidRows(): array
    {
        return $this->invalidRows;
    }

    /** @return array */
    public function getUnknownUsers(): array
    {
        return $this->unknownUsers;
    }
}
