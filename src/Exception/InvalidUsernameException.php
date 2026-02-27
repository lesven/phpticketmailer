<?php
declare(strict_types=1);

namespace App\Exception;

/**
 * Exception für ungültige Benutzernamen
 * 
 * Wird geworfen wenn ein Benutzername nicht den Validierungsregeln entspricht.
 */
final class InvalidUsernameException extends \InvalidArgumentException
{
}