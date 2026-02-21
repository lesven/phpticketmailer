<?php

namespace App\Tests\Service;

use App\Dto\EmailConfig;
use App\Service\EmailService;
use App\Service\Email\EmailConfigFactory;
use App\Service\Email\EmailContentRenderer;
use App\Service\Email\EmailSender;
use App\Service\Email\EmailRecordFactory;
use App\Entity\EmailSent;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use App\Repository\UserRepository;
use App\Repository\EmailSentRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EmailServiceTest extends TestCase
{
    private EmailService $service;

    private $configFactory;
    private $contentRenderer;
    private $emailSender;
    private $recordFactory;
    private $userRepo;
    private $emailSentRepo;
    private $eventDispatcher;

    private EmailConfig $defaultConfig;

    protected function setUp(): void
    {
        $this->configFactory = $this->createMock(EmailConfigFactory::class);
        $this->contentRenderer = $this->createMock(EmailContentRenderer::class);
        $this->emailSender = $this->createMock(EmailSender::class);
        $this->recordFactory = $this->createMock(EmailRecordFactory::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->emailSentRepo = $this->createMock(EmailSentRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->defaultConfig = new EmailConfig(
            subject: 'Ihre Rückmeldung zu Ticket {{ticketId}}',
            ticketBaseUrl: 'https://tickets.example',
            senderEmail: 'noreply@example.com',
            senderName: 'Ticket-System',
            testEmail: 'test@example.com',
            useCustomSMTP: false,
        );

        $this->configFactory->method('create')->willReturn($this->defaultConfig);
        $this->contentRenderer->method('getGlobalTemplate')
            ->willReturn('<p>Template {{username}} {{ticketId}}</p>');
        $this->contentRenderer->method('resolveTemplateForTicket')
            ->willReturn('<p>Template {{username}} {{ticketId}}</p>');
        $this->contentRenderer->method('getTemplateDebugInfo')
            ->willReturn([]);

        // Default: recordFactory persist returns the record unchanged
        $this->recordFactory->method('persist')
            ->willReturnCallback(fn(EmailSent $r) => $r);

        // Default: recordFactory createSkippedRecord
        $this->recordFactory->method('createSkippedRecord')
            ->willReturnCallback(function (TicketData $td, \DateTime $ts, bool $testMode, $status) {
                $rec = new EmailSent();
                $rec->setTicketId($td->ticketId);
                $rec->setUsername((string) $td->username);
                $rec->setEmail(null);
                $rec->setSubject('');
                $rec->setStatus($status);
                $rec->setTimestamp(clone $ts);
                $rec->setTestMode($testMode);
                $rec->setTicketName($td->ticketName);
                $rec->setTicketCreated($td->created ?? null);
                return $rec;
            });

        // Default: recordFactory createSendRecord
        $this->recordFactory->method('createSendRecord')
            ->willReturnCallback(function (TicketData $td, \DateTime $ts, bool $testMode) {
                $rec = new EmailSent();
                $rec->setTicketId($td->ticketId);
                $rec->setUsername((string) $td->username);
                $rec->setTimestamp(clone $ts);
                $rec->setTestMode($testMode);
                $rec->setTicketName($td->ticketName);
                $rec->setTicketCreated($td->created ?? null);
                return $rec;
            });

        $this->service = new EmailService(
            $this->configFactory,
            $this->contentRenderer,
            $this->emailSender,
            $this->recordFactory,
            $this->userRepo,
            $this->emailSentRepo,
            $this->eventDispatcher,
        );
    }

    public function testSendTicketEmailsSendsAndMarksSent(): void
    {
        $ticket = TicketData::fromStrings('T-001', 'user1', 'Demo');

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('user1@example.com'));
        $user->method('isExcludedFromSurveys')->willReturn(false);

        $this->userRepo->method('findByUsername')->with('user1')->willReturn($user);
        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        $this->contentRenderer->method('render')->willReturn('rendered content');
        $this->emailSender->expects($this->once())->method('send');

        $result = $this->service->sendTicketEmails([$ticket], false);

        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
        $this->assertEquals(EmailAddress::fromString('user1@example.com'), $result[0]->getEmail());
    }

    public function testProcessTicketEmailWhenUserNotFound(): void
    {
        $ticket = TicketData::fromStrings('T-999', 'nouser', 'Missing');

        $this->userRepo->method('findByUsername')->with('nouser')->willReturn(null);
        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        $result = $this->service->sendTicketEmailsWithDuplicateCheck([$ticket], false, false);

        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::error('no email found'), $result[0]->getStatus());
        $this->assertEquals(EmailAddress::fromString('example@example.com'), $result[0]->getEmail());
    }

    public function testProcessTicketEmailWhenMailerThrowsSetsErrorStatus(): void
    {
        $ticket = TicketData::fromStrings('T-002', 'user2', 'Demo2');

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('user2@example.com'));
        $user->method('isExcludedFromSurveys')->willReturn(false);

        $this->userRepo->method('findByUsername')->with('user2')->willReturn($user);
        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        $this->contentRenderer->method('render')->willReturn('content');
        $this->emailSender->method('send')->willThrowException(new \Exception('SMTP down'));

        $result = $this->service->sendTicketEmailsWithDuplicateCheck([$ticket], false, false);

        $this->assertCount(1, $result);
        $this->assertStringStartsWith('Fehler:', $result[0]->getStatus()->getValue());
    }

    public function testSendTicketEmailsWithDuplicateInCsv(): void
    {
        $ticket = TicketData::fromStrings('DUP-001', 'dupuser', 'Dup');
        $tickets = [$ticket, $ticket];

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('dup@example.com'));
        $user->method('isExcludedFromSurveys')->willReturn(false);

        $this->userRepo->method('findByUsername')->willReturn($user);
        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        $this->contentRenderer->method('render')->willReturn('content');
        $this->emailSender->expects($this->once())->method('send');

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

        $this->emailSentRepo->method('findExistingTickets')->willReturn(['EXT-001' => $existing]);
        $this->userRepo->method('findByUsername')->willReturn(null);

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Bereits verarbeitet am', $result[0]->getStatus()->getValue());
        $this->assertStringContainsString('02.01.2025', $result[0]->getStatus()->getValue());
    }

    public function testSendTicketEmailsUserExcludedCreatesSkippedRecord(): void
    {
        $ticket = TicketData::fromStrings('EXC-001', 'ex', 'Ex');
        $tickets = [$ticket];

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('ex@example.com'));
        $user->method('isExcludedFromSurveys')->willReturn(true);

        $this->userRepo->method('findByUsername')->willReturn($user);
        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Von Umfragen ausgeschlossen', $result[0]->getStatus());
    }

    public function testSendTicketEmailsWithForceResendIgnoresExistingTickets(): void
    {
        $ticket = TicketData::fromStrings('FRC-001', 'fuser', 'Force');
        $tickets = [$ticket];

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('f@example.com'));
        $user->method('isExcludedFromSurveys')->willReturn(false);

        $this->userRepo->method('findByUsername')->willReturn($user);

        $this->contentRenderer->method('render')->willReturn('content');
        $this->emailSender->expects($this->once())->method('send');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, true);

        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
    }

    public function testSendTicketEmailsHandlesPersistException(): void
    {
        $ticket = TicketData::fromStrings('ERR1', 'erruser', 'Err');
        $tickets = [$ticket];

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('err@example.com'));
        $user->method('isExcludedFromSurveys')->willReturn(false);

        $this->userRepo->method('findByUsername')->willReturn($user);
        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);
        $this->contentRenderer->method('render')->willReturn('content');
        $this->emailSender->expects($this->once())->method('send');

        // Simulate persist failure returning error record
        $errorRecord = new EmailSent();
        $errorRecord->setTicketId($ticket->ticketId);
        $errorRecord->setUsername((string) $ticket->username);
        $errorRecord->setStatus(EmailStatus::error('database save failed - db error'));

        $recordFactory = $this->createMock(EmailRecordFactory::class);
        $recordFactory->method('createSendRecord')
            ->willReturnCallback(function (TicketData $td, \DateTime $ts, bool $testMode) {
                $rec = new EmailSent();
                $rec->setTicketId($td->ticketId);
                $rec->setUsername((string) $td->username);
                $rec->setTimestamp(clone $ts);
                $rec->setTestMode($testMode);
                $rec->setTicketName($td->ticketName);
                $rec->setTicketCreated($td->created ?? null);
                return $rec;
            });
        $recordFactory->method('persist')->willReturn($errorRecord);

        $service = new EmailService(
            $this->configFactory,
            $this->contentRenderer,
            $this->emailSender,
            $recordFactory,
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

        $customConfig = new EmailConfig(
            subject: 'Ihre Rückmeldung zu Ticket {{ticketId}}',
            ticketBaseUrl: 'https://tickets.example',
            senderEmail: 'noreply@example.com',
            senderName: 'Ticket-System',
            testEmail: $customTestEmail,
            useCustomSMTP: false,
        );
        $configFactory = $this->createMock(EmailConfigFactory::class);
        $configFactory->method('create')->with(true, $customTestEmail)->willReturn($customConfig);

        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('jsmith@example.com'));
        $user->method('isExcludedFromSurveys')->willReturn(false);
        $this->userRepo->method('findByUsername')->willReturn($user);

        $this->contentRenderer->method('render')->willReturn('content');
        $this->emailSender->expects($this->once())->method('send')
            ->with($customTestEmail, $this->anything(), $this->anything(), $this->anything());

        $service = new EmailService(
            $configFactory,
            $this->contentRenderer,
            $this->emailSender,
            $this->recordFactory,
            $this->userRepo,
            $this->emailSentRepo,
            $this->eventDispatcher,
        );

        $result = $service->sendTicketEmailsWithDuplicateCheck($tickets, true, false, $customTestEmail);

        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
    }

    public function testSendTicketEmailsUsesDefaultTestEmailWhenCustomIsEmpty(): void
    {
        $ticket = TicketData::fromStrings('T-123', 'jsmith', 'Problem');
        $tickets = [$ticket];

        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('jsmith@example.com'));
        $user->method('isExcludedFromSurveys')->willReturn(false);
        $this->userRepo->method('findByUsername')->willReturn($user);

        $this->contentRenderer->method('render')->willReturn('content');
        $this->emailSender->expects($this->once())->method('send')
            ->with('test@example.com', $this->anything(), $this->anything(), $this->anything());

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, true, false, '');

        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
    }

    public function testSendTicketEmailsCallsWrapper(): void
    {
        $ticket = TicketData::fromStrings('WRP-001', 'wuser', 'Wrap');
        $tickets = [$ticket];

        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);
        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('w@example.com'));
        $user->method('isExcludedFromSurveys')->willReturn(false);
        $this->userRepo->method('findByUsername')->willReturn($user);

        $this->contentRenderer->method('render')->willReturn('content');
        $this->emailSender->expects($this->once())->method('send');

        $result = $this->service->sendTicketEmails($tickets, false);
        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
    }

    public function testGetTemplateDebugInfoDelegatesToContentRenderer(): void
    {
        $debugInfo = ['T-1' => ['selectionMethod' => 'date_match']];
        $contentRenderer = $this->createMock(EmailContentRenderer::class);
        $contentRenderer->method('getTemplateDebugInfo')->willReturn($debugInfo);

        $service = new EmailService(
            $this->configFactory,
            $contentRenderer,
            $this->emailSender,
            $this->recordFactory,
            $this->userRepo,
            $this->emailSentRepo,
            $this->eventDispatcher,
        );

        $this->assertEquals($debugInfo, $service->getTemplateDebugInfo());
    }
}
