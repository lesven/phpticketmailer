<?php
declare(strict_types=1);

namespace App\Exception;

/**
 * Exception für ungültige Ticket-IDs
 * 
 * Wird geworfen wenn eine Ticket-ID nicht den Validierungsregeln entspricht.
 */
final class InvalidTicketIdException extends \InvalidArgumentException
{
}