<?php

namespace App\Exception;

/**
 * Exception für ungültige Ticket-Namen
 *
 * Wird geworfen, wenn ein Ticket-Name die Validierungsregeln verletzt.
 */
class InvalidTicketNameException extends \InvalidArgumentException
{
}
