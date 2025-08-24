<?php

namespace App\Exception;

/**
 * Exception für ungültige Ticket-IDs
 * 
 * Wird geworfen wenn eine Ticket-ID nicht den Validierungsregeln entspricht.
 */
class InvalidTicketIdException extends \InvalidArgumentException
{
}