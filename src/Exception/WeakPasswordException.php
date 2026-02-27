<?php
declare(strict_types=1);

namespace App\Exception;

/**
 * Exception für schwache oder ungültige Passwörter
 */
final class WeakPasswordException extends \DomainException
{
}