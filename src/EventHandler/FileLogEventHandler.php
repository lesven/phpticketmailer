<?php
declare(strict_types=1);

namespace App\EventHandler;

use App\Event\User\UserImportStartedEvent;
use App\Event\User\UserImportedEvent;
use App\Event\User\UserImportCompletedEvent;
use App\Event\Email\EmailSentEvent;
use App\Event\Email\EmailFailedEvent;
use App\Event\Email\BulkEmailCompletedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event Handler der direkt in Dateien loggt
 * 
 * Schreibt Domain Events strukturiert in Log-Dateien im var/log Verzeichnis.
 * Funktioniert ohne Monolog-Bundle.
 */
final class FileLogEventHandler
{
    private readonly string $logDir;

    public function __construct(string $projectDir)
    {
        $this->logDir = $projectDir . '/var/log';
        
        // Sicherstellen, dass Log-Verzeichnis existiert
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    #[AsEventListener]
    public function onUserImportStarted(UserImportStartedEvent $event): void
    {
        $this->writeLog('audit.log', 'User import started', [
            'event' => 'user_import_started',
            'filename' => $event->filename,
            'total_rows' => $event->totalRows,
            'clear_existing' => $event->clearExisting,
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[AsEventListener]
    public function onUserImported(UserImportedEvent $event): void
    {
        $this->writeLog('audit.log', 'User imported', [
            'event' => 'user_imported',
            'username' => (string) $event->username,
            'email' => (string) $event->email,
            'excluded_from_surveys' => $event->excludedFromSurveys,
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[AsEventListener]
    public function onUserImportCompleted(UserImportCompletedEvent $event): void
    {
        $this->writeLog('audit.log', 'User import completed', [
            'event' => 'user_import_completed',
            'filename' => $event->filename,
            'success_count' => $event->successCount,
            'error_count' => $event->errorCount,
            'total_processed' => $event->getTotalProcessed(),
            'success_rate' => round($event->getSuccessRate(), 2),
            'duration_seconds' => $event->durationInSeconds,
            'was_successful' => $event->wasSuccessful(),
            'errors' => $event->errors,
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
        ]);

        // Statistiken separat loggen
        $this->writeLog('statistics.log', 'Import statistics', [
            'type' => 'user_import',
            'date' => $event->getOccurredAt()->format('Y-m-d'),
            'hour' => $event->getOccurredAt()->format('H'),
            'filename' => $event->filename,
            'total_processed' => $event->getTotalProcessed(),
            'success_count' => $event->successCount,
            'error_count' => $event->errorCount,
            'success_rate' => $event->getSuccessRate(),
            'duration_seconds' => $event->durationInSeconds
        ]);
    }

    #[AsEventListener]
    public function onEmailSent(EmailSentEvent $event): void
    {
        $this->writeLog('audit.log', 'Email sent', [
            'event' => 'email_sent',
            'ticket_id' => (string) $event->ticketData->ticketId,
            'username' => (string) $event->ticketData->username,
            'email' => (string) $event->email,
            'subject' => $event->subject,
            'test_mode' => $event->testMode,
            'ticket_name' => $event->ticketData->ticketName?->getValue(),
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
        ]);

        // Email-Statistik
        $this->writeLog('statistics.log', 'Email sent', [
            'type' => 'email_counter',
            'email_type' => 'sent',
            'test_mode' => $event->testMode,
            'date' => $event->getOccurredAt()->format('Y-m-d'),
            'hour' => $event->getOccurredAt()->format('H'),
            'count' => 1
        ]);
    }

    #[AsEventListener]
    public function onEmailFailed(EmailFailedEvent $event): void
    {
        $this->writeLog('audit.log', 'Email failed', [
            'event' => 'email_failed',
            'ticket_id' => (string) $event->ticketData->ticketId,
            'username' => (string) $event->ticketData->username,
            'email' => (string) $event->email,
            'subject' => $event->subject,
            'error_message' => $event->errorMessage,
            'test_mode' => $event->testMode,
            'ticket_name' => $event->ticketData->ticketName?->getValue(),
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[AsEventListener]
    public function onBulkEmailCompleted(BulkEmailCompletedEvent $event): void
    {
        $this->writeLog('audit.log', 'Bulk email completed', [
            'log_level' => $event->wasSuccessful() ? 'INFO' : 'WARNING',
            'event' => 'bulk_email_completed',
            'total_emails' => $event->totalEmails,
            'sent_count' => $event->sentCount,
            'failed_count' => $event->failedCount,
            'skipped_count' => $event->skippedCount,
            'success_rate' => round($event->getSuccessRate(), 2),
            'test_mode' => $event->testMode,
            'duration_seconds' => $event->durationInSeconds,
            'was_successful' => $event->wasSuccessful(),
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
        ]);
    }

    private function writeLog(string $filename, string $message, array $context = []): void
    {
        $timestamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] %s %s\n",
            $timestamp,
            $message,
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        file_put_contents(
            $this->logDir . '/' . $filename,
            $logEntry,
            FILE_APPEND | LOCK_EX
        );
    }
}