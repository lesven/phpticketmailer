<?php

namespace App\Tests\EventHandler;

use App\EventHandler\AuditLogEventHandler;
use App\Event\User\UserImportStartedEvent;
use App\Event\User\UserImportedEvent;
use App\Event\User\UserImportCompletedEvent;
use App\Event\Email\EmailSentEvent;
use App\Event\Email\EmailFailedEvent;
use App\Event\Email\BulkEmailCompletedEvent;
use App\ValueObject\EmailAddress;
use App\ValueObject\Username;
use App\ValueObject\TicketData;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AuditLogEventHandlerTest extends TestCase
{
    private LoggerInterface $logger;
    private AuditLogEventHandler $handler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new AuditLogEventHandler($this->logger);
    }

    public function testOnUserImportStartedLogsInfo(): void
    {
        $event = new UserImportStartedEvent(100, 'users.csv', false);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'User import started',
                $this->callback(function (array $context) use ($event) {
                    return $context['event'] === 'user_import_started'
                        && $context['filename'] === 'users.csv'
                        && $context['total_rows'] === 100
                        && $context['clear_existing'] === false;
                })
            );

        $this->handler->onUserImportStarted($event);
    }

    public function testOnUserImportedLogsInfo(): void
    {
        $username = Username::fromString('john.doe');
        $email = EmailAddress::fromString('john@example.com');
        $event = new UserImportedEvent($username, $email, false);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'User imported',
                $this->callback(function (array $context) {
                    return $context['event'] === 'user_imported'
                        && $context['username'] === 'john.doe'
                        && $context['email'] === 'john@example.com'
                        && $context['excluded_from_surveys'] === false;
                })
            );

        $this->handler->onUserImported($event);
    }

    public function testOnUserImportCompletedLogsInfo(): void
    {
        $event = new UserImportCompletedEvent(90, 10, ['row 5: invalid email'], 'users.csv', 1.5);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'User import completed',
                $this->callback(function (array $context) {
                    return $context['event'] === 'user_import_completed'
                        && $context['success_count'] === 90
                        && $context['error_count'] === 10
                        && $context['total_processed'] === 100
                        && $context['filename'] === 'users.csv';
                })
            );

        $this->handler->onUserImportCompleted($event);
    }

    public function testOnEmailSentLogsInfo(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-123', 'john.doe', 'System Issue');
        $email = EmailAddress::fromString('john@example.com');
        $event = new EmailSentEvent($ticketData, $email, 'Your ticket update', false);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Email sent',
                $this->callback(function (array $context) {
                    return $context['event'] === 'email_sent'
                        && $context['ticket_id'] === 'TICKET-123'
                        && $context['username'] === 'john.doe'
                        && $context['email'] === 'john@example.com'
                        && $context['test_mode'] === false;
                })
            );

        $this->handler->onEmailSent($event);
    }

    public function testOnEmailFailedLogsWarning(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-456', 'jane.doe');
        $email = EmailAddress::fromString('jane@example.com');
        $event = new EmailFailedEvent($ticketData, $email, 'Your ticket update', 'SMTP connection failed', true);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Email failed',
                $this->callback(function (array $context) {
                    return $context['event'] === 'email_failed'
                        && $context['ticket_id'] === 'TICKET-456'
                        && $context['error_message'] === 'SMTP connection failed'
                        && $context['test_mode'] === true;
                })
            );

        $this->handler->onEmailFailed($event);
    }

    public function testOnBulkEmailCompletedLogsInfoWhenSuccessful(): void
    {
        $event = new BulkEmailCompletedEvent(100, 95, 0, 5, false, 12.5);

        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                'info',
                'Bulk email completed',
                $this->callback(function (array $context) {
                    return $context['event'] === 'bulk_email_completed'
                        && $context['total_emails'] === 100
                        && $context['sent_count'] === 95
                        && $context['failed_count'] === 0
                        && $context['was_successful'] === true;
                })
            );

        $this->handler->onBulkEmailCompleted($event);
    }

    public function testOnBulkEmailCompletedLogsWarningWhenUnsuccessful(): void
    {
        $event = new BulkEmailCompletedEvent(100, 80, 20, 0, false, 15.0);

        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                'warning',
                'Bulk email completed',
                $this->callback(function (array $context) {
                    return $context['event'] === 'bulk_email_completed'
                        && $context['failed_count'] === 20
                        && $context['was_successful'] === false;
                })
            );

        $this->handler->onBulkEmailCompleted($event);
    }

    public function testOnUserImportStartedWithClearExisting(): void
    {
        $event = new UserImportStartedEvent(50, 'import.csv', true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'User import started',
                $this->callback(function (array $context) {
                    return $context['clear_existing'] === true
                        && $context['total_rows'] === 50;
                })
            );

        $this->handler->onUserImportStarted($event);
    }

    public function testOnEmailSentInTestMode(): void
    {
        $ticketData = TicketData::fromStrings('T-001', 'tester');
        $email = EmailAddress::fromString('tester@example.com');
        $event = new EmailSentEvent($ticketData, $email, 'Test email', true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Email sent',
                $this->callback(function (array $context) {
                    return $context['test_mode'] === true;
                })
            );

        $this->handler->onEmailSent($event);
    }
}
