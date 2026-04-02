<?php

namespace App\Tests\Integration;

use App\Event\Email\BulkEmailCompletedEvent;
use App\Event\Email\EmailFailedEvent;
use App\Event\Email\EmailSentEvent;
use App\Event\User\UserImportCompletedEvent;
use App\Event\User\UserImportStartedEvent;
use App\Event\User\UserImportedEvent;
use App\EventHandler\AdminNotificationEventHandler;
use App\EventHandler\AuditLogEventHandler;
use App\EventHandler\FileLogEventHandler;
use App\EventHandler\StatisticsEventHandler;
use App\ValueObject\EmailAddress;
use App\ValueObject\TicketData;
use App\ValueObject\Username;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration Test: Alle Event-Handler reagieren korrekt auf Domain Events
 */
class EventHandlerChainIntegrationTest extends KernelTestCase
{
    public function testAllHandlersAreRegisteredAsServices(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertInstanceOf(AuditLogEventHandler::class, $container->get(AuditLogEventHandler::class));
        $this->assertInstanceOf(FileLogEventHandler::class, $container->get(FileLogEventHandler::class));
        $this->assertInstanceOf(StatisticsEventHandler::class, $container->get(StatisticsEventHandler::class));
        $this->assertInstanceOf(AdminNotificationEventHandler::class, $container->get(AdminNotificationEventHandler::class));
    }

    public function testAuditHandlerRespondesToUserImportStarted(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with('User import started', $this->arrayHasKey('event'));

        $handler = new AuditLogEventHandler($mockLogger);
        $event = new UserImportStartedEvent(50, 'import.csv', false);

        $handler->onUserImportStarted($event);
    }

    public function testAuditHandlerRespondesToUserImported(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with('User imported', $this->callback(fn($ctx) =>
                $ctx['event'] === 'user_imported' && $ctx['username'] === 'new_user'
            ));

        $handler = new AuditLogEventHandler($mockLogger);
        $event = new UserImportedEvent(
            Username::fromString('new_user'),
            EmailAddress::fromString('new@example.com'),
            false
        );

        $handler->onUserImported($event);
    }

    public function testAuditHandlerRespondesToUserImportCompleted(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with('User import completed', $this->callback(fn($ctx) =>
                $ctx['event'] === 'user_import_completed' &&
                $ctx['success_count'] === 48 &&
                $ctx['error_count'] === 2
            ));

        $handler = new AuditLogEventHandler($mockLogger);
        $event = new UserImportCompletedEvent(48, 2, [], 'import.csv', 1.2);

        $handler->onUserImportCompleted($event);
    }

    public function testAuditHandlerRespondesToEmailSent(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with('Email sent', $this->callback(fn($ctx) =>
                $ctx['event'] === 'email_sent' && $ctx['test_mode'] === false
            ));

        $handler = new AuditLogEventHandler($mockLogger);
        $event = new EmailSentEvent(
            TicketData::fromStrings('T-001', 'user_a', 'Bug'),
            EmailAddress::fromString('a@example.com'),
            'Your Ticket',
            false
        );

        $handler->onEmailSent($event);
    }

    public function testAuditHandlerRespondesToEmailFailed(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('warning')
            ->with('Email failed', $this->callback(fn($ctx) =>
                $ctx['event'] === 'email_failed' && isset($ctx['error_message'])
            ));

        $handler = new AuditLogEventHandler($mockLogger);
        $event = new EmailFailedEvent(
            TicketData::fromStrings('T-002', 'user_b'),
            EmailAddress::fromString('b@example.com'),
            'Subject',
            'SMTP connection refused'
        );

        $handler->onEmailFailed($event);
    }

    public function testAuditHandlerRespondesToBulkCompleted(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        // wasSuccessful() = true → log('info', ...)
        $mockLogger->expects($this->once())
            ->method('log')
            ->with('info', 'Bulk email completed', $this->callback(fn($ctx) =>
                $ctx['total_emails'] === 5 && $ctx['was_successful'] === true
            ));

        $handler = new AuditLogEventHandler($mockLogger);
        $event = new BulkEmailCompletedEvent(5, 5, 0, 0, false, 1.0);

        $handler->onBulkEmailCompleted($event);
    }

    public function testStatisticsHandlerRespondesToEmailSent(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with('Statistics', $this->callback(fn($ctx) =>
                $ctx['email_type'] === 'sent' && $ctx['count'] === 1
            ));

        $mockEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $handler = new StatisticsEventHandler($mockEm, $mockLogger);
        $event = new EmailSentEvent(
            TicketData::fromStrings('T-STAT', 'stat_user'),
            EmailAddress::fromString('stat@example.com'),
            'Subject',
            false
        );

        $handler->onEmailSent($event);
    }

    public function testStatisticsHandlerRespondesToEmailFailed(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with('Statistics', $this->callback(fn($ctx) =>
                $ctx['email_type'] === 'failed'
            ));

        $mockEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $handler = new StatisticsEventHandler($mockEm, $mockLogger);
        $event = new EmailFailedEvent(
            TicketData::fromStrings('T-FAIL', 'fail_user'),
            EmailAddress::fromString('fail@example.com'),
            'Subject',
            'Error msg'
        );

        $handler->onEmailFailed($event);
    }

    public function testFileLogHandlerWritesToAuditLog(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_filelog_' . uniqid();
        mkdir($tempDir, 0755, true);

        $handler = new FileLogEventHandler($tempDir);
        $event = new UserImportStartedEvent(10, 'test.csv', false);

        $handler->onUserImportStarted($event);

        $auditLogPath = $tempDir . '/var/log/audit.log';
        $this->assertFileExists($auditLogPath);
        $logContent = file_get_contents($auditLogPath);
        $this->assertStringContainsString('User import started', $logContent);
        $this->assertStringContainsString('test.csv', $logContent);

        // Cleanup
        array_map('unlink', glob($tempDir . '/var/log/*'));
        @rmdir($tempDir . '/var/log');
        @rmdir($tempDir . '/var');
        @rmdir($tempDir);
    }
}
