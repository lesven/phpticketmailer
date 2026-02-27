<?php
declare(strict_types=1);

namespace App\Dto;

use App\ValueObject\TicketData;
use App\ValueObject\UnknownUserWithTicket;

/**
 * Typisiertes Ergebnis der CSV-Verarbeitung.
 *
 * Ersetzt den untypisierten Array-Return von CsvProcessor::process()
 * und stellt sicher, dass unknownUsers nur UnknownUserWithTicket-Objekte enthält.
 */
final readonly class CsvProcessingResult
{
    /**
     * @param TicketData[] $validTickets Erfolgreich geparste Tickets
     * @param array<int, array{rowNumber: int, data: array, error?: string}> $invalidRows Ungültige CSV-Zeilen
     * @param UnknownUserWithTicket[] $unknownUsers Unbekannte Benutzer mit Ticket-Kontext
     */
    public function __construct(
        public array $validTickets = [],
        public array $invalidRows = [],
        public array $unknownUsers = [],
    ) {
    }
}
