<?php
declare(strict_types=1);

namespace App\Exception;

/**
 * Basis-Exception für alle Ticket-Mailer-spezifischen Fehler
 * 
 * Alle fachlichen Exceptions der Anwendung sollten von dieser Klasse erben,
 * um eine einheitliche Fehlerbehandlung zu ermöglichen.
 */
abstract class TicketMailerException extends \Exception
{
    /**
     * Erstellt eine neue Exception mit verbesserter Kontextinformation
     * 
     * @param string $message Fehlermeldung
     * @param int $code Fehlercode
     * @param \Throwable|null $previous Ursprüngliche Exception
     * @param array $context Zusätzlicher Kontext für das Debugging
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Gibt den zusätzlichen Kontext der Exception zurück
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Erstellt eine Benutzer-freundliche Fehlermeldung
     */
    public function getUserMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * Gibt Debugging-Informationen zurück
     */
    public function getDebugInfo(): array
    {
        return [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context
        ];
    }
}
