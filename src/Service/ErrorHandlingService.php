<?php

namespace App\Service;

use App\Exception\TicketMailerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Service für einheitliche Fehlerbehandlung in der Anwendung
 * 
 * Stellt zentrale Methoden für die Behandlung und Protokollierung 
 * von Fehlern zur Verfügung und sorgt für konsistente Benutzer-Feedback.
 */
class ErrorHandlingService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly FlashBagInterface $flashBag
    ) {
    }

    /**
     * Behandelt eine TicketMailerException mit Logging und Benutzer-Feedback
     * 
     * @param TicketMailerException $exception Die zu behandelnde Exception
     * @param string $context Zusätzlicher Kontext für das Logging
     */
    public function handleTicketMailerException(TicketMailerException $exception, string $context = ''): void
    {
        // Debug-Informationen loggen
        $this->logger->error('TicketMailer Exception occurred', [
            'context' => $context,
            'exception' => $exception->getDebugInfo(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Benutzer-freundliche Nachricht anzeigen
        $this->flashBag->add('error', $exception->getUserMessage());
    }

    /**
     * Behandelt eine allgemeine Exception mit fallback Behandlung
     * 
     * @param \Throwable $exception Die zu behandelnde Exception
     * @param string $context Zusätzlicher Kontext für das Logging
     * @param string $userMessage Benutzerdefinierte Nachricht für den Benutzer
     */
    public function handleGeneralException(
        \Throwable $exception, 
        string $context = '', 
        string $userMessage = 'Ein unerwarteter Fehler ist aufgetreten.'
    ): void {
        // Detaillierte Informationen loggen
        $this->logger->error('Unexpected exception occurred', [
            'context' => $context,
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Benutzer-Nachricht anzeigen
        $this->flashBag->add('error', $userMessage);
    }

    /**
     * Loggt eine Warnung mit optionalem Benutzer-Feedback
     * 
     * @param string $message Die Warn-Nachricht
     * @param array $context Zusätzlicher Kontext für das Logging
     * @param string|null $userMessage Optionale Nachricht für den Benutzer
     */
    public function logWarning(string $message, array $context = [], ?string $userMessage = null): void
    {
        $this->logger->warning($message, $context);

        if ($userMessage !== null) {
            $this->flashBag->add('warning', $userMessage);
        }
    }

    /**
     * Loggt eine Info-Nachricht mit optionalem Benutzer-Feedback
     * 
     * @param string $message Die Info-Nachricht
     * @param array $context Zusätzlicher Kontext für das Logging
     * @param string|null $userMessage Optionale Nachricht für den Benutzer
     */
    public function logInfo(string $message, array $context = [], ?string $userMessage = null): void
    {
        $this->logger->info($message, $context);

        if ($userMessage !== null) {
            $this->flashBag->add('info', $userMessage);
        }
    }

    /**
     * Erstellt eine standardisierte Erfolgs-Nachricht
     * 
     * @param string $message Die Erfolgs-Nachricht
     * @param array $context Zusätzlicher Kontext für das Logging
     */
    public function logSuccess(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
        $this->flashBag->add('success', $message);
    }

    /**
     * Prüft, ob eine Exception als kritisch eingestuft werden sollte
     * 
     * @param \Throwable $exception Die zu prüfende Exception
     * @return bool True, wenn die Exception kritisch ist
     */
    public function isCriticalException(\Throwable $exception): bool
    {
        // Datenbankfehler, Sicherheitsfehler etc. als kritisch einstufen
        $criticalExceptions = [
            \Doctrine\DBAL\Exception::class,
            \Symfony\Component\Security\Core\Exception\AuthenticationException::class,
            \Symfony\Component\Mailer\Exception\TransportException::class
        ];

        foreach ($criticalExceptions as $criticalClass) {
            if ($exception instanceof $criticalClass) {
                return true;
            }
        }

        return false;
    }
}
