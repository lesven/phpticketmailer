<?php

namespace App\Tests\Integration;

use App\Event\Email\BulkEmailCompletedEvent;
use App\Event\Email\EmailSentEvent;
use App\EventHandler\AuditLogEventHandler;
use App\EventHandler\FileLogEventHandler;
use App\EventHandler\StatisticsEventHandler;
use App\Service\EmailService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Integration Test für den vollständigen E-Mail-Workflow:
 * EmailService + Event-Dispatcher + Event-Handler
 */
class EmailWorkflowIntegrationTest extends KernelTestCase
{
    public function testEmailServiceIsAvailableInContainer(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $emailService = $container->get(EmailService::class);

        $this->assertInstanceOf(EmailService::class, $emailService);
    }

    public function testAuditLogHandlerRegisteredInContainer(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $handler = $container->get(AuditLogEventHandler::class);

        $this->assertInstanceOf(AuditLogEventHandler::class, $handler);
    }

    public function testStatisticsHandlerRegisteredInContainer(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $handler = $container->get(StatisticsEventHandler::class);

        $this->assertInstanceOf(StatisticsEventHandler::class, $handler);
    }

    public function testFileLogHandlerRegisteredInContainer(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $handler = $container->get(FileLogEventHandler::class);

        $this->assertInstanceOf(FileLogEventHandler::class, $handler);
    }

    public function testEmailSentEventIsLoggedByAuditHandler(): void
    {
        self::bootKernel();

        $ticketData = \App\ValueObject\TicketData::fromStrings('T-INT-001', 'test_user', 'Integration Test');
        $email = \App\ValueObject\EmailAddress::fromString('test@example.com');

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with(
                'Email sent',
                $this->callback(fn($ctx) =>
                    isset($ctx['event']) && $ctx['event'] === 'email_sent' &&
                    $ctx['ticket_id'] === 'T-INT-001' &&
                    $ctx['test_mode'] === true
                )
            );

        $handler = new AuditLogEventHandler($mockLogger);
        $event = new EmailSentEvent($ticketData, $email, 'Test Subject', true);

        $handler->onEmailSent($event);
    }

    public function testBulkEmailCompletedEventIsLoggedByAuditHandler(): void
    {
        self::bootKernel();

        $mockLogger = $this->createMock(LoggerInterface::class);
        // wasSuccessful() = true → log('info', ...)
        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                'info',
                'Bulk email completed',
                $this->callback(fn($ctx) =>
                    isset($ctx['event']) && $ctx['event'] === 'bulk_email_completed' &&
                    $ctx['total_emails'] === 10 &&
                    $ctx['sent_count'] === 8
                )
            );

        $handler = new AuditLogEventHandler($mockLogger);
        $event = new BulkEmailCompletedEvent(10, 8, 0, 2, false, 1.5);

        $handler->onBulkEmailCompleted($event);
    }

    public function testBulkEmailWithFailuresLogsAsWarning(): void
    {
        self::bootKernel();

        $mockLogger = $this->createMock(LoggerInterface::class);
        // wasSuccessful() = false (3 failed) → log('warning', ...)
        $mockLogger->expects($this->once())
            ->method('log')
            ->with('warning', 'Bulk email completed', $this->anything());

        $handler = new AuditLogEventHandler($mockLogger);
        $event = new BulkEmailCompletedEvent(10, 7, 3, 0, false, 1.0);

        $handler->onBulkEmailCompleted($event);
    }

    public function testStatisticsHandlerLogsEmailSentStatistics(): void
    {
        self::bootKernel();

        $ticketData = \App\ValueObject\TicketData::fromStrings('T-STAT-01', 'stat_user');
        $email = \App\ValueObject\EmailAddress::fromString('stat@example.com');

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with(
                'Statistics',
                $this->callback(fn($ctx) =>
                    isset($ctx['email_type']) && $ctx['email_type'] === 'sent'
                )
            );

        $mockEntityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $handler = new StatisticsEventHandler($mockEntityManager, $mockLogger);
        $event = new EmailSentEvent($ticketData, $email, 'Subject', false);

        $handler->onEmailSent($event);
    }

    public function testEventDispatcherIsAvailable(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $dispatcher = $container->get(EventDispatcherInterface::class);

        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    }
}
