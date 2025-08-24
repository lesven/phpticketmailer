<?php

namespace App\Exception;

/**
 * Exception für ungültige Benutzernamen
 * 
 * Wird geworfen wenn ein Benutzername nicht den Validierungsregeln entspricht.
 */
class InvalidUsernameException extends \InvalidArgumentException
{
}