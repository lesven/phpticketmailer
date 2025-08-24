<?php

namespace App\EventHandler;

use App\Event\User\UserImportCompletedEvent;
use App\Event\Email\EmailSentEvent;
use App\Event\Email\EmailFailedEvent;
use App\Event\Email\BulkEmailCompletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event Handler für Statistiken
 * 
 * Sammelt und persistiert Statistiken basierend auf Domain Events.
 * Ermöglicht Reporting und Analytics ohne die Haupt-Services zu koppeln.
 */
class StatisticsEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $statisticsLogger
    ) {}

    #[AsEventListener]
    public function onUserImportCompleted(UserImportCompletedEvent $event): void
    {
        // Hier könnten wir eine Statistics Entity haben
        // Für jetzt loggen wir die Statistiken strukturiert
        
        $statistics = [
            'type' => 'user_import',
            'date' => $event->getOccurredAt()->format('Y-m-d'),
            'hour' => $event->getOccurredAt()->format('H'),
            'filename' => $event->filename,
            'total_processed' => $event->getTotalProcessed(),
            'success_count' => $event->successCount,
            'error_count' => $event->errorCount,
            'success_rate' => $event->getSuccessRate(),
            'duration_seconds' => $event->durationInSeconds
        ];
        
        // In einer echten Implementierung würden wir das in eine Statistics-Tabelle schreiben
        // Für jetzt verwenden wir strukturiertes Logging
        $this->statisticsLogger->info('User import completed', $statistics);
    }

    #[AsEventListener]
    public function onEmailSent(EmailSentEvent $event): void
    {
        $this->incrementEmailCounter('sent', $event->testMode, $event->getOccurredAt());
    }

    #[AsEventListener]
    public function onEmailFailed(EmailFailedEvent $event): void
    {
        $this->incrementEmailCounter('failed', $event->testMode, $event->getOccurredAt());
    }

    #[AsEventListener]
    public function onBulkEmailCompleted(BulkEmailCompletedEvent $event): void
    {
        $statistics = [
            'type' => 'bulk_email',
            'date' => $event->getOccurredAt()->format('Y-m-d'),
            'hour' => $event->getOccurredAt()->format('H'),
            'total_emails' => $event->totalEmails,
            'sent_count' => $event->sentCount,
            'failed_count' => $event->failedCount,
            'skipped_count' => $event->skippedCount,
            'success_rate' => $event->getSuccessRate(),
            'duration_seconds' => $event->durationInSeconds,
            'test_mode' => $event->testMode
        ];
        
        $this->statisticsLogger->info('Statistics', $statistics);
    }

    /**
     * Hilfsmethode zum Inkrementieren von E-Mail-Zählern
     */
    private function incrementEmailCounter(string $type, bool $testMode, \DateTimeImmutable $occurredAt): void
    {
        $statistics = [
            'type' => 'email_counter',
            'email_type' => $type, // 'sent' oder 'failed'
            'test_mode' => $testMode,
            'date' => $occurredAt->format('Y-m-d'),
            'hour' => $occurredAt->format('H'),
            'count' => 1
        ];
        
        $this->statisticsLogger->info('Statistics', $statistics);
    }
}