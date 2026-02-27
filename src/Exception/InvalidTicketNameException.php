<?php
declare(strict_types=1);

namespace App\Exception;

/**
 * Exception für ungültige Ticket-Namen
 *
 * Wird geworfen, wenn ein Ticket-Name die Validierungsregeln verletzt.
 */
final class InvalidTicketNameException extends \InvalidArgumentException
{
}
