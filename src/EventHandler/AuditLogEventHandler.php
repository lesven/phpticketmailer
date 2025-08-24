<?php

namespace App\EventHandler;

use App\Event\User\UserImportStartedEvent;
use App\Event\User\UserImportedEvent;
use App\Event\User\UserImportCompletedEvent;
use App\Event\Email\EmailSentEvent;
use App\Event\Email\EmailFailedEvent;
use App\Event\Email\BulkEmailCompletedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event Handler für Audit Logging
 * 
 * Reagiert auf Domain Events und erstellt detaillierte Audit Logs
 * für Compliance und Nachverfolgbarkeit aller wichtigen Geschäftsereignisse.
 */
class AuditLogEventHandler
{
    public function __construct(
        private readonly LoggerInterface $auditLogger
    ) {}

    #[AsEventListener]
    public function onUserImportStarted(UserImportStartedEvent $event): void
    {
        $this->auditLogger->info('User import started', [
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
        $this->auditLogger->info('User imported', [
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
        $this->auditLogger->info('User import completed', [
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
    }

    #[AsEventListener]
    public function onEmailSent(EmailSentEvent $event): void
    {
        $this->auditLogger->info('Email sent', [
            'event' => 'email_sent',
            'ticket_id' => (string) $event->ticketId,
            'username' => (string) $event->username,
            'email' => (string) $event->email,
            'subject' => $event->subject,
            'test_mode' => $event->testMode,
            'ticket_name' => $event->ticketName,
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[AsEventListener]
    public function onEmailFailed(EmailFailedEvent $event): void
    {
        $this->auditLogger->warning('Email failed', [
            'event' => 'email_failed',
            'ticket_id' => (string) $event->ticketId,
            'username' => (string) $event->username,
            'email' => (string) $event->email,
            'subject' => $event->subject,
            'error_message' => $event->errorMessage,
            'test_mode' => $event->testMode,
            'ticket_name' => $event->ticketName,
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[AsEventListener]
    public function onBulkEmailCompleted(BulkEmailCompletedEvent $event): void
    {
        $logLevel = $event->wasSuccessful() ? 'info' : 'warning';
        
        $this->auditLogger->log($logLevel, 'Bulk email completed', [
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
}