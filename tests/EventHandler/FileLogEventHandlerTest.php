<?php

namespace App\Tests\EventHandler;

use App\EventHandler\FileLogEventHandler;
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

class FileLogEventHandlerTest extends TestCase
{
    private string $tempDir;
    private FileLogEventHandler $handler;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/file_log_handler_test_' . uniqid();
        mkdir($this->tempDir);
        $this->handler = new FileLogEventHandler($this->tempDir);
    }

    protected function tearDown(): void
    {
        $logDir = $this->tempDir . '/var/log';
        if (is_dir($logDir)) {
            foreach (glob($logDir . '/*') as $file) {
                unlink($file);
            }
            rmdir($logDir);
            rmdir($this->tempDir . '/var');
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    private function readLogFile(string $filename): string
    {
        $path = $this->tempDir . '/var/log/' . $filename;
        return file_exists($path) ? file_get_contents($path) : '';
    }

    public function testConstructorCreatesLogDirectory(): void
    {
        $this->assertDirectoryExists($this->tempDir . '/var/log');
    }

    public function testOnUserImportStartedWritesToAuditLog(): void
    {
        $event = new UserImportStartedEvent(150, 'users.csv', false);

        $this->handler->onUserImportStarted($event);

        $logContent = $this->readLogFile('audit.log');
        $this->assertStringContainsString('User import started', $logContent);
        $this->assertStringContainsString('user_import_started', $logContent);
        $this->assertStringContainsString('users.csv', $logContent);
        $this->assertStringContainsString('"total_rows":150', $logContent);
    }

    public function testOnUserImportedWritesToAuditLog(): void
    {
        $username = Username::fromString('john.doe');
        $email = EmailAddress::fromString('john@example.com');
        $event = new UserImportedEvent($username, $email, true);

        $this->handler->onUserImported($event);

        $logContent = $this->readLogFile('audit.log');
        $this->assertStringContainsString('User imported', $logContent);
        $this->assertStringContainsString('user_imported', $logContent);
        $this->assertStringContainsString('john.doe', $logContent);
        $this->assertStringContainsString('john@example.com', $logContent);
    }

    public function testOnUserImportCompletedWritesToAuditLog(): void
    {
        $event = new UserImportCompletedEvent(80, 20, ['row 5: error'], 'batch.csv', 3.0);

        $this->handler->onUserImportCompleted($event);

        $logContent = $this->readLogFile('audit.log');
        $this->assertStringContainsString('User import completed', $logContent);
        $this->assertStringContainsString('user_import_completed', $logContent);
        $this->assertStringContainsString('"success_count":80', $logContent);
        $this->assertStringContainsString('"error_count":20', $logContent);
    }

    public function testOnUserImportCompletedWritesToStatisticsLog(): void
    {
        $event = new UserImportCompletedEvent(80, 20, [], 'batch.csv', 3.0);

        $this->handler->onUserImportCompleted($event);

        $logContent = $this->readLogFile('statistics.log');
        $this->assertStringContainsString('Import statistics', $logContent);
        $this->assertStringContainsString('user_import', $logContent);
        $this->assertStringContainsString('batch.csv', $logContent);
    }

    public function testOnEmailSentWritesToAuditLog(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-001', 'alice', 'Bug Report');
        $email = EmailAddress::fromString('alice@example.com');
        $event = new EmailSentEvent($ticketData, $email, 'Your ticket update', false);

        $this->handler->onEmailSent($event);

        $logContent = $this->readLogFile('audit.log');
        $this->assertStringContainsString('Email sent', $logContent);
        $this->assertStringContainsString('email_sent', $logContent);
        $this->assertStringContainsString('TICKET-001', $logContent);
        $this->assertStringContainsString('alice', $logContent);
    }

    public function testOnEmailSentWritesToStatisticsLog(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-002', 'bob');
        $email = EmailAddress::fromString('bob@example.com');
        $event = new EmailSentEvent($ticketData, $email, 'Subject', true);

        $this->handler->onEmailSent($event);

        $logContent = $this->readLogFile('statistics.log');
        $this->assertStringContainsString('Email sent', $logContent);
        $this->assertStringContainsString('email_counter', $logContent);
        $this->assertStringContainsString('"email_type":"sent"', $logContent);
    }

    public function testOnEmailFailedWritesToAuditLog(): void
    {
        $ticketData = TicketData::fromStrings('TICKET-003', 'charlie');
        $email = EmailAddress::fromString('charlie@example.com');
        $event = new EmailFailedEvent($ticketData, $email, 'Subject', 'SMTP timeout', false);

        $this->handler->onEmailFailed($event);

        $logContent = $this->readLogFile('audit.log');
        $this->assertStringContainsString('Email failed', $logContent);
        $this->assertStringContainsString('email_failed', $logContent);
        $this->assertStringContainsString('SMTP timeout', $logContent);
        $this->assertStringContainsString('TICKET-003', $logContent);
    }

    public function testOnBulkEmailCompletedWritesToAuditLog(): void
    {
        $event = new BulkEmailCompletedEvent(50, 45, 3, 2, false, 10.0);

        $this->handler->onBulkEmailCompleted($event);

        $logContent = $this->readLogFile('audit.log');
        $this->assertStringContainsString('Bulk email completed', $logContent);
        $this->assertStringContainsString('bulk_email_completed', $logContent);
        $this->assertStringContainsString('"total_emails":50', $logContent);
    }

    public function testLogEntriesContainTimestamp(): void
    {
        $event = new UserImportStartedEvent(10, 'test.csv');

        $this->handler->onUserImportStarted($event);

        $logContent = $this->readLogFile('audit.log');
        // Timestamp format: [YYYY-MM-DD HH:MM:SS]
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $logContent);
    }

    public function testMultipleEventsAppendToLogFile(): void
    {
        $event1 = new UserImportStartedEvent(10, 'first.csv');
        $event2 = new UserImportStartedEvent(20, 'second.csv');

        $this->handler->onUserImportStarted($event1);
        $this->handler->onUserImportStarted($event2);

        $logContent = $this->readLogFile('audit.log');
        $this->assertStringContainsString('first.csv', $logContent);
        $this->assertStringContainsString('second.csv', $logContent);
    }

    public function testOnBulkEmailCompletedWasSuccessfulFlag(): void
    {
        // Successful event (no failed)
        $successEvent = new BulkEmailCompletedEvent(10, 10, 0, 0, false, 1.0);
        $this->handler->onBulkEmailCompleted($successEvent);

        $logContent = $this->readLogFile('audit.log');
        $this->assertStringContainsString('"was_successful":true', $logContent);
    }
}
