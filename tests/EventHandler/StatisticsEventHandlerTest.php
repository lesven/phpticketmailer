<?php

namespace App\Tests\EventHandler;

use App\EventHandler\StatisticsEventHandler;
use App\Event\User\UserImportCompletedEvent;
use App\Event\Email\EmailSentEvent;
use App\Event\Email\EmailFailedEvent;
use App\Event\Email\BulkEmailCompletedEvent;
use App\ValueObject\EmailAddress;
use App\ValueObject\TicketData;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StatisticsEventHandlerTest extends TestCase
{
    private LoggerInterface $statisticsLogger;
    private EntityManagerInterface $entityManager;
    private StatisticsEventHandler $handler;

    protected function setUp(): void
    {
        $this->statisticsLogger = $this->createMock(LoggerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->handler = new StatisticsEventHandler($this->entityManager, $this->statisticsLogger);
    }

    public function testOnUserImportCompletedLogsStatistics(): void
    {
        $event = new UserImportCompletedEvent(90, 10, [], 'users.csv', 2.5);

        $this->statisticsLogger->expects($this->once())
            ->method('info')
            ->with(
                'User import completed',
                $this->callback(function (array $context) {
                    return $context['type'] === 'user_import'
                        && $context['filename'] === 'users.csv'
                        && $context['total_processed'] === 100
                        && $context['success_count'] === 90
                        && $context['error_count'] === 10
                        && $context['duration_seconds'] === 2.5;
                })
            );

        $this->handler->onUserImportCompleted($event);
    }

    public function testOnUserImportCompletedIncludesSuccessRate(): void
    {
        $event = new UserImportCompletedEvent(75, 25, [], 'import.csv', 1.0);

        $this->statisticsLogger->expects($this->once())
            ->method('info')
            ->with(
                'User import completed',
                $this->callback(function (array $context) {
                    return $context['success_rate'] === 75.0;
                })
            );

        $this->handler->onUserImportCompleted($event);
    }

    public function testOnEmailSentLogsCounter(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-001', 'john.doe');
        $email = EmailAddress::fromString('john@example.com');
        $event = new EmailSentEvent($ticketData, $email, 'Your ticket', false);

        $this->statisticsLogger->expects($this->once())
            ->method('info')
            ->with(
                'Statistics',
                $this->callback(function (array $context) {
                    return $context['type'] === 'email_counter'
                        && $context['email_type'] === 'sent'
                        && $context['test_mode'] === false
                        && $context['count'] === 1;
                })
            );

        $this->handler->onEmailSent($event);
    }

    public function testOnEmailSentInTestModeLogsCorrectFlag(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-002', 'jane.doe');
        $email = EmailAddress::fromString('jane@example.com');
        $event = new EmailSentEvent($ticketData, $email, 'Test email', true);

        $this->statisticsLogger->expects($this->once())
            ->method('info')
            ->with(
                'Statistics',
                $this->callback(function (array $context) {
                    return $context['test_mode'] === true;
                })
            );

        $this->handler->onEmailSent($event);
    }

    public function testOnEmailFailedLogsCounter(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-003', 'bob.smith');
        $email = EmailAddress::fromString('bob@example.com');
        $event = new EmailFailedEvent($ticketData, $email, 'Ticket update', 'Connection timeout', false);

        $this->statisticsLogger->expects($this->once())
            ->method('info')
            ->with(
                'Statistics',
                $this->callback(function (array $context) {
                    return $context['type'] === 'email_counter'
                        && $context['email_type'] === 'failed'
                        && $context['count'] === 1;
                })
            );

        $this->handler->onEmailFailed($event);
    }

    public function testOnBulkEmailCompletedLogsStatistics(): void
    {
        $event = new BulkEmailCompletedEvent(100, 85, 5, 10, false, 30.0);

        $this->statisticsLogger->expects($this->once())
            ->method('info')
            ->with(
                'Statistics',
                $this->callback(function (array $context) {
                    return $context['type'] === 'bulk_email'
                        && $context['total_emails'] === 100
                        && $context['sent_count'] === 85
                        && $context['failed_count'] === 5
                        && $context['skipped_count'] === 10
                        && $context['test_mode'] === false
                        && $context['duration_seconds'] === 30.0;
                })
            );

        $this->handler->onBulkEmailCompleted($event);
    }

    public function testOnBulkEmailCompletedIncludesSuccessRate(): void
    {
        $event = new BulkEmailCompletedEvent(100, 60, 40, 0, true, 5.0);

        $this->statisticsLogger->expects($this->once())
            ->method('info')
            ->with(
                'Statistics',
                $this->callback(function (array $context) {
                    return $context['success_rate'] === 60.0
                        && $context['test_mode'] === true;
                })
            );

        $this->handler->onBulkEmailCompleted($event);
    }

    public function testEmailCounterLogsDateAndHour(): void
    {
        $ticketData = TicketData::fromStrings('T-100', 'alice');
        $email = EmailAddress::fromString('alice@example.com');
        $event = new EmailSentEvent($ticketData, $email, 'Subject', false);

        $this->statisticsLogger->expects($this->once())
            ->method('info')
            ->with(
                'Statistics',
                $this->callback(function (array $context) {
                    return isset($context['date'])
                        && isset($context['hour'])
                        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $context['date'])
                        && preg_match('/^\d{2}$/', $context['hour']);
                })
            );

        $this->handler->onEmailSent($event);
    }
}
