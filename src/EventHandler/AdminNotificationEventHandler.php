<?php

namespace App\EventHandler;

use App\Event\User\UserImportCompletedEvent;
use App\Event\Email\BulkEmailCompletedEvent;
use App\Event\Email\EmailFailedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event Handler für Admin-Benachrichtigungen
 * 
 * Sendet Benachrichtigungen an Administratoren bei wichtigen Ereignissen,
 * besonders bei Fehlern oder abgeschlossenen Batch-Operationen.
 */
class AdminNotificationEventHandler
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    #[AsEventListener]
    public function onUserImportCompleted(UserImportCompletedEvent $event): void
    {
        // Benachrichtige Admins nur bei Fehlern oder großen Importen
        if (!$event->wasSuccessful() || $event->getTotalProcessed() > 100) {
            $this->notifyAdminsAboutUserImport($event);
        }
    }

    #[AsEventListener]
    public function onBulkEmailCompleted(BulkEmailCompletedEvent $event): void
    {
        // Benachrichtige Admins bei Fehlern oder großen Batch-Läufen
        if (!$event->wasSuccessful() || $event->totalEmails > 50) {
            $this->notifyAdminsAboutBulkEmail($event);
        }
    }

    #[AsEventListener]
    public function onEmailFailed(EmailFailedEvent $event): void
    {
        // Bei kritischen E-Mail-Fehlern sofort benachrichtigen
        if ($this->isCriticalEmailError($event->errorMessage)) {
            $this->notifyAdminsAboutEmailFailure($event);
        }
    }

    private function notifyAdminsAboutUserImport(UserImportCompletedEvent $event): void
    {
        $message = sprintf(
            'User Import abgeschlossen: %s (Erfolg: %d, Fehler: %d, Rate: %.1f%%)',
            $event->filename,
            $event->successCount,
            $event->errorCount,
            $event->getSuccessRate()
        );

        $this->logger->notice('[ADMIN_NOTIFICATION] ' . $message, [
            'event_type' => 'user_import_completed',
            'filename' => $event->filename,
            'statistics' => [
                'success_count' => $event->successCount,
                'error_count' => $event->errorCount,
                'success_rate' => $event->getSuccessRate(),
                'duration' => $event->durationInSeconds
            ],
            'errors' => $event->errors
        ]);
    }

    private function notifyAdminsAboutBulkEmail(BulkEmailCompletedEvent $event): void
    {
        $message = sprintf(
            'Bulk E-Mail-Versand abgeschlossen: %d E-Mails (Versendet: %d, Fehler: %d, Rate: %.1f%%)',
            $event->totalEmails,
            $event->sentCount,
            $event->failedCount,
            $event->getSuccessRate()
        );

        $this->logger->notice('[ADMIN_NOTIFICATION] ' . $message, [
            'event_type' => 'bulk_email_completed',
            'statistics' => [
                'total_emails' => $event->totalEmails,
                'sent_count' => $event->sentCount,
                'failed_count' => $event->failedCount,
                'skipped_count' => $event->skippedCount,
                'success_rate' => $event->getSuccessRate(),
                'duration' => $event->durationInSeconds
            ],
            'test_mode' => $event->testMode
        ]);
    }

    private function notifyAdminsAboutEmailFailure(EmailFailedEvent $event): void
    {
        $message = sprintf(
            'Kritischer E-Mail-Fehler für Ticket %s (User: %s): %s',
            (string) $event->ticketId,
            (string) $event->username,
            $event->errorMessage
        );

        $this->logger->error('[ADMIN_NOTIFICATION] ' . $message, [
            'event_type' => 'critical_email_failure',
            'ticket_id' => (string) $event->ticketId,
            'username' => (string) $event->username,
            'email' => (string) $event->email,
            'error_message' => $event->errorMessage,
            'test_mode' => $event->testMode
        ]);
    }

    private function isCriticalEmailError(string $errorMessage): bool
    {
        $criticalPatterns = [
            'SMTP connect() failed',
            'SMTP Error: Could not authenticate',
            'Connection refused',
            'Network is unreachable',
            'SSL certificate problem'
        ];

        foreach ($criticalPatterns as $pattern) {
            if (stripos($errorMessage, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}