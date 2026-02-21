<?php

namespace App\Tests\EventHandler;

use App\EventHandler\AdminNotificationEventHandler;
use App\Event\User\UserImportCompletedEvent;
use App\Event\Email\EmailFailedEvent;
use App\Event\Email\BulkEmailCompletedEvent;
use App\ValueObject\EmailAddress;
use App\ValueObject\TicketData;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AdminNotificationEventHandlerTest extends TestCase
{
    private LoggerInterface $logger;
    private AdminNotificationEventHandler $handler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new AdminNotificationEventHandler($this->logger);
    }

    // --- UserImportCompleted ---

    public function testOnUserImportCompletedDoesNotNotifyWhenSuccessfulAndSmall(): void
    {
        // Successful (no errors) and <= 100 users: no notification expected
        $event = new UserImportCompletedEvent(50, 0, [], 'users.csv', 1.0);

        $this->logger->expects($this->never())->method('notice');
        $this->logger->expects($this->never())->method('error');

        $this->handler->onUserImportCompleted($event);
    }

    public function testOnUserImportCompletedNotifiesWhenUnsuccessful(): void
    {
        // Has errors -> should notify
        $event = new UserImportCompletedEvent(40, 10, ['row 3: invalid'], 'users.csv', 2.0);

        $this->logger->expects($this->once())
            ->method('notice')
            ->with(
                $this->stringContains('[ADMIN_NOTIFICATION]'),
                $this->callback(function (array $context) {
                    return $context['event_type'] === 'user_import_completed';
                })
            );

        $this->handler->onUserImportCompleted($event);
    }

    public function testOnUserImportCompletedNotifiesWhenMoreThan100Users(): void
    {
        // More than 100 processed users (even without errors)
        $event = new UserImportCompletedEvent(101, 0, [], 'large_import.csv', 5.0);

        $this->logger->expects($this->once())
            ->method('notice')
            ->with($this->stringContains('[ADMIN_NOTIFICATION]'));

        $this->handler->onUserImportCompleted($event);
    }

    public function testOnUserImportCompletedNotifiesAtExactly100Users(): void
    {
        // Exactly 100: total is 100 which is NOT > 100, and no errors -> no notification
        $event = new UserImportCompletedEvent(100, 0, [], 'import.csv', 3.0);

        $this->logger->expects($this->never())->method('notice');

        $this->handler->onUserImportCompleted($event);
    }

    // --- BulkEmailCompleted ---

    public function testOnBulkEmailCompletedDoesNotNotifyWhenSuccessfulAndSmall(): void
    {
        // Successful and <= 50 emails: no notification
        $event = new BulkEmailCompletedEvent(30, 30, 0, 0, false, 5.0);

        $this->logger->expects($this->never())->method('notice');

        $this->handler->onBulkEmailCompleted($event);
    }

    public function testOnBulkEmailCompletedNotifiesWhenUnsuccessful(): void
    {
        // Has failed emails -> should notify
        $event = new BulkEmailCompletedEvent(20, 15, 5, 0, false, 3.0);

        $this->logger->expects($this->once())
            ->method('notice')
            ->with(
                $this->stringContains('[ADMIN_NOTIFICATION]'),
                $this->callback(function (array $context) {
                    return $context['event_type'] === 'bulk_email_completed';
                })
            );

        $this->handler->onBulkEmailCompleted($event);
    }

    public function testOnBulkEmailCompletedNotifiesWhenMoreThan50Emails(): void
    {
        // More than 50 total emails (even without failures)
        $event = new BulkEmailCompletedEvent(51, 51, 0, 0, false, 10.0);

        $this->logger->expects($this->once())
            ->method('notice')
            ->with($this->stringContains('[ADMIN_NOTIFICATION]'));

        $this->handler->onBulkEmailCompleted($event);
    }

    public function testOnBulkEmailCompletedNotifiesAtExactly50Emails(): void
    {
        // Exactly 50 total emails with no failures: NOT > 50, no notification
        $event = new BulkEmailCompletedEvent(50, 50, 0, 0, false, 8.0);

        $this->logger->expects($this->never())->method('notice');

        $this->handler->onBulkEmailCompleted($event);
    }

    // --- EmailFailed (critical error detection) ---

    public function testOnEmailFailedDoesNotNotifyForNonCriticalErrors(): void
    {
        $ticketData = TicketData::fromStrings('T-100', 'john.doe');
        $email = EmailAddress::fromString('john@example.com');
        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'Invalid recipient address', false);

        $this->logger->expects($this->never())->method('error');

        $this->handler->onEmailFailed($event);
    }

    public function testOnEmailFailedNotifiesForSmtpConnectFailure(): void
    {
        $ticketData = TicketData::fromStrings('T-200', 'jane.doe');
        $email = EmailAddress::fromString('jane@example.com');
        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'SMTP connect() failed', false);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('[ADMIN_NOTIFICATION]'),
                $this->callback(function (array $context) {
                    return $context['event_type'] === 'critical_email_failure';
                })
            );

        $this->handler->onEmailFailed($event);
    }

    public function testOnEmailFailedNotifiesForAuthenticationFailure(): void
    {
        $ticketData = TicketData::fromStrings('T-300', 'bob.smith');
        $email = EmailAddress::fromString('bob@example.com');
        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'SMTP Error: Could not authenticate', false);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('[ADMIN_NOTIFICATION]'));

        $this->handler->onEmailFailed($event);
    }

    public function testOnEmailFailedNotifiesForConnectionRefused(): void
    {
        $ticketData = TicketData::fromStrings('T-400', 'alice');
        $email = EmailAddress::fromString('alice@example.com');
        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'Connection refused', false);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('[ADMIN_NOTIFICATION]'));

        $this->handler->onEmailFailed($event);
    }

    public function testOnEmailFailedNotifiesForNetworkUnreachable(): void
    {
        $ticketData = TicketData::fromStrings('T-500', 'charlie');
        $email = EmailAddress::fromString('charlie@example.com');
        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'Network is unreachable', false);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('[ADMIN_NOTIFICATION]'));

        $this->handler->onEmailFailed($event);
    }

    public function testOnEmailFailedNotifiesForSslCertificateProblem(): void
    {
        $ticketData = TicketData::fromStrings('T-600', 'dave');
        $email = EmailAddress::fromString('dave@example.com');
        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'SSL certificate problem: self signed certificate', false);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('[ADMIN_NOTIFICATION]'));

        $this->handler->onEmailFailed($event);
    }

    public function testOnEmailFailedIsCaseInsensitiveForCriticalPatterns(): void
    {
        $ticketData = TicketData::fromStrings('T-700', 'eve');
        $email = EmailAddress::fromString('eve@example.com');
        // Using uppercase to check case-insensitive matching
        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'smtp connect() failed', false);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('[ADMIN_NOTIFICATION]'));

        $this->handler->onEmailFailed($event);
    }

    public function testOnEmailFailedIncludesTicketInfoInNotification(): void
    {
        $ticketData = TicketData::fromStrings('T-999', 'frank');
        $email = EmailAddress::fromString('frank@example.com');
        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'SMTP connect() failed', false);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('T-999'),
                $this->callback(function (array $context) {
                    return $context['ticket_id'] === 'T-999'
                        && $context['username'] === 'frank'
                        && $context['email'] === 'frank@example.com';
                })
            );

        $this->handler->onEmailFailed($event);
    }
}
