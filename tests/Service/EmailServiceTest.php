<?php

namespace App\Tests\Service;

use App\Dto\EmailConfig;
use App\Dto\TemplateResolutionResult;
use App\Entity\EmailSent;
use App\Entity\User;
use App\Repository\EmailSentRepository;
use App\Repository\UserRepository;
use App\Service\EmailComposer;
use App\Service\EmailRecordService;
use App\Service\EmailService;
use App\Service\EmailTransportService;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EmailServiceTest extends TestCase
{
    private EmailService $service;

    private EmailTransportService $transportService;
    private EmailRecordService $recordService;
    private EmailComposer $composer;
    private UserRepository $userRepo;
    private EmailSentRepository $emailSentRepo;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->transportService = $this->createMock(EmailTransportService::class);
        $this->recordService = $this->createMock(EmailRecordService::class);
        $this->composer = $this->createMock(EmailComposer::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->emailSentRepo = $this->createMock(EmailSentRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        // Default: transport returns a config
        $this->transportService->method('buildEmailConfig')
            ->willReturn(new EmailConfig(
                subject: 'Ihre Rückmeldung zu Ticket {{ticketId}}',
                ticketBaseUrl: 'https://tickets.example',
                senderEmail: 'noreply@example.com',
                senderName: 'Ticket-System',
                testEmail: 'test@example.com',
                useCustomSMTP: false,
            ));

        // Default: composer returns template content
        $this->composer->method('getDefaultContent')
            ->willReturn('<p>Template {{username}} {{ticketId}}</p>');

        $this->composer->method('resolveTemplateContent')
            ->willReturn('<p>Template content</p>');

        $this->composer->method('prepareEmailContent')
            ->willReturnCallback(function (string $template, TicketData $ticket, ?User $user, string $baseUrl, bool $testMode): string {
                $body = str_replace(
                    ['{{username}}', '{{ticketId}}'],
                    [(string) $ticket->username, (string) $ticket->ticketId],
                    $template
                );
                if ($testMode && $user !== null) {
                    $body = "*** TESTMODUS - E-Mail wäre an {$user->getEmail()} gegangen ***\n\n" . $body;
                }
                return $body;
            });

        $this->composer->method('getTemplateDebugInfo')
            ->willReturn([]);

        // Default: record service persists and returns the record as-is
        $this->recordService->method('persistEmailRecord')
            ->willReturnCallback(fn(EmailSent $r) => $r);

        // Default: record service creates skip records
        $this->recordService->method('createAndDispatchSkippedRecord')
            ->willReturnCallback(function (TicketData $td, \DateTime $ts, bool $test, EmailStatus $status) {
                $record = new EmailSent();
                $record->setTicketId($td->ticketId);
                $record->setUsername((string) $td->username);
                $record->setStatus($status);
                $record->setTimestamp(clone $ts);
                $record->setTestMode($test);
                $record->setEmail(null);
                $record->setSubject('');
                $record->setTicketName($td->ticketName);
                $record->setTicketCreated($td->created ?? null);
                return $record;
            });

        // Ensure SMTP repo returns empty by default
        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        $this->service = new EmailService(
            $this->transportService,
            $this->recordService,
            $this->composer,
            $this->userRepo,
            $this->emailSentRepo,
            $this->eventDispatcher,
        );
    }

    public function testSendTicketEmailsWithDuplicateInCsv(): void
    {
        $ticket = TicketData::fromStrings('DUP-001', 'dupuser', 'Dup');
        $tickets = [$ticket, $ticket];

        $user = new User();
        $user->setEmail('dup@example.com');
        $user->setUsername('dupuser');

        $this->userRepo->method('findByUsername')->willReturn($user);

        // transportService should send only once (second is CSV duplicate)
        $this->transportService->expects($this->once())->method('sendEmail');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);

        $this->assertCount(2, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
        $this->assertStringContainsString('Mehrfach in CSV', $result[1]->getStatus()->getValue());
    }

    public function testSendTicketEmailsWithExistingTicketInDb(): void
    {
        $ticket = TicketData::fromStrings('EXT-001', 'euser', 'Exist');
        $tickets = [$ticket];

        $existing = new EmailSent();
        $existing->setTimestamp(new \DateTime('2025-01-02'));

        // Need a fresh emailSentRepo mock that returns the existing ticket
        $emailSentRepo = $this->createMock(EmailSentRepository::class);
        $emailSentRepo->method('findExistingTickets')->willReturn(['EXT-001' => $existing]);

        $service = new EmailService(
            $this->transportService,
            $this->recordService,
            $this->composer,
            $this->userRepo,
            $emailSentRepo,
            $this->eventDispatcher,
        );

        $result = $service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Bereits verarbeitet am', $result[0]->getStatus()->getValue());
        $this->assertStringContainsString('02.01.2025', $result[0]->getStatus()->getValue());
    }

    public function testSendTicketEmailsUserExcludedCreatesSkippedRecord(): void
    {
        $ticket = TicketData::fromStrings('EXC-001', 'ex', 'Ex');
        $tickets = [$ticket];

        $user = new User();
        $user->setEmail('ex@example.com');
        $user->setUsername('ex');
        $user->setExcludedFromSurveys(true);

        $this->userRepo->method('findByUsername')->willReturn($user);

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Von Umfragen ausgeschlossen', $result[0]->getStatus());
    }

    public function testSendTicketEmailsWithForceResendIgnoresExistingTickets(): void
    {
        $ticket = TicketData::fromStrings('FRC-001', 'fuser', 'Force');
        $tickets = [$ticket];

        $user = new User();
        $user->setEmail('f@example.com');
        $user->setUsername('fuser');
        $this->userRepo->method('findByUsername')->willReturn($user);

        $this->transportService->expects($this->once())->method('sendEmail');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, true);

        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
    }

    public function testSendTicketEmailsHandlesPersistFlushExceptionCreatesErrorRecord(): void
    {
        $ticket = TicketData::fromStrings('ERR1', 'erruser', 'Err');
        $tickets = [$ticket];

        $user = new User();
        $user->setEmail('err@example.com');
        $user->setUsername('erruser');
        $this->userRepo->method('findByUsername')->willReturn($user);

        $this->transportService->expects($this->once())->method('sendEmail');

        // Need a fresh recordService mock that simulates a persist error
        $recordService = $this->createMock(EmailRecordService::class);
        $recordService->method('persistEmailRecord')
            ->willReturnCallback(function (EmailSent $r) {
                $errorRecord = new EmailSent();
                $errorRecord->setTicketId($r->getTicketId());
                $errorRecord->setUsername($r->getUsername());
                $errorRecord->setTimestamp($r->getTimestamp());
                $errorRecord->setTestMode($r->getTestMode());
                $errorRecord->setStatus(EmailStatus::error('database save failed - db error'));
                $errorRecord->setEmail($r->getEmail());
                $errorRecord->setSubject($r->getSubject());
                return $errorRecord;
            });

        $service = new EmailService(
            $this->transportService,
            $recordService,
            $this->composer,
            $this->userRepo,
            $this->emailSentRepo,
            $this->eventDispatcher,
        );

        $result = $service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Fehler: database save failed', $result[0]->getStatus()->getValue());
    }

    public function testSendTicketEmailsWithCustomTestEmail(): void
    {
        $ticket = TicketData::fromStrings('T-123', 'jsmith', 'Problem');
        $tickets = [$ticket];
        $customTestEmail = 'custom-test@example.com';

        // Override transport config to return custom test email
        $transportService = $this->createMock(EmailTransportService::class);
        $transportService->method('buildEmailConfig')
            ->willReturn(new EmailConfig(
                subject: 'Ihre Rückmeldung zu Ticket {{ticketId}}',
                ticketBaseUrl: 'https://tickets.example',
                senderEmail: 'noreply@example.com',
                senderName: 'Ticket-System',
                testEmail: $customTestEmail,
                useCustomSMTP: false,
            ));

        $sentEmailArg = null;
        $transportService->expects($this->once())
            ->method('sendEmail')
            ->willReturnCallback(function ($recipient) use (&$sentEmailArg) {
                $sentEmailArg = $recipient;
            });

        $service = new EmailService(
            $transportService,
            $this->recordService,
            $this->composer,
            $this->userRepo,
            $this->emailSentRepo,
            $this->eventDispatcher,
        );

        $user = new User();
        $user->setEmail('jsmith@example.com');
        $user->setUsername('jsmith');
        $this->userRepo->method('findByUsername')->willReturn($user);

        $result = $service->sendTicketEmailsWithDuplicateCheck($tickets, true, false, $customTestEmail);

        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
        $this->assertEquals($customTestEmail, (string) $sentEmailArg);
    }

    public function testSendTicketEmailsUsesDefaultTestEmailWhenCustomIsEmpty(): void
    {
        $ticket = TicketData::fromStrings('T-123', 'jsmith', 'Problem');
        $tickets = [$ticket];

        $sentEmailArg = null;
        $this->transportService->expects($this->once())
            ->method('sendEmail')
            ->willReturnCallback(function ($recipient) use (&$sentEmailArg) {
                $sentEmailArg = $recipient;
            });

        $user = new User();
        $user->setEmail('jsmith@example.com');
        $user->setUsername('jsmith');
        $this->userRepo->method('findByUsername')->willReturn($user);

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, true, false, '');

        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
        $this->assertEquals('test@example.com', (string) $sentEmailArg);
    }

    public function testSendTicketEmailsUserNotFoundCreatesSkipRecord(): void
    {
        $ticket = TicketData::fromStrings('T-999', 'nouser', 'Missing');
        $tickets = [$ticket];

        $this->userRepo->method('findByUsername')->willReturn(null);

        // Transport should NOT be called (no user → skip)
        $this->transportService->expects($this->never())->method('sendEmail');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('no email found', $result[0]->getStatus()->getValue());
    }

    public function testSendTicketEmailsWhenMailerThrowsSetsErrorStatus(): void
    {
        $ticket = TicketData::fromStrings('T-002', 'user2', 'Demo2');
        $tickets = [$ticket];

        $user = new User();
        $user->setEmail('user2@example.com');
        $user->setUsername('user2');
        $this->userRepo->method('findByUsername')->willReturn($user);

        $this->transportService->method('sendEmail')
            ->willThrowException(new \Exception('SMTP down'));

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);

        $this->assertCount(1, $result);
        $this->assertStringStartsWith('Fehler:', $result[0]->getStatus()->getValue());
    }

    public function testGetTemplateDebugInfoDelegatesToComposer(): void
    {
        $debugInfo = ['T-1' => ['selectionMethod' => 'test']];
        $composer = $this->createMock(EmailComposer::class);
        $composer->method('getTemplateDebugInfo')->willReturn($debugInfo);

        $service = new EmailService(
            $this->transportService,
            $this->recordService,
            $composer,
            $this->userRepo,
            $this->emailSentRepo,
            $this->eventDispatcher,
        );

        $this->assertEquals($debugInfo, $service->getTemplateDebugInfo());
    }
}
