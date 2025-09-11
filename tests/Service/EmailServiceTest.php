<?php

namespace App\Tests\Service;

use App\Service\EmailService;
use App\Entity\EmailSent;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Repository\SMTPConfigRepository;
use App\Repository\EmailSentRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EmailServiceTest extends TestCase
{
    private EmailService $service;

    private $mailer;
    private $entityManager;
    private $userRepo;
    private $smtpRepo;
    private $emailSentRepo;
    private $params;
    private $eventDispatcher;
    private $prevErrorLog;

    protected function setUp(): void
    {
    // silence error_log output during tests to avoid noisy messages
    $this->prevErrorLog = ini_get('error_log');
    @ini_set('error_log', '/dev/null');

        $this->mailer = $this->createMock(MailerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $this->emailSentRepo = $this->createMock(EmailSentRepository::class);
        $this->params = $this->createMock(ParameterBagInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        // sensible defaults for parameters used in EmailService
        $this->params->method('get')->willReturnCallback(function($key, $default = null) {
            $map = [
                'app.email_subject' => 'Ihre Rückmeldung zu Ticket {{ticketId}}',
                'app.ticket_base_url' => 'https://tickets.example',
                'app.test_email' => 'test@example.com',
                'app.sender_email' => 'noreply@example.com',
                'app.sender_name' => 'Ticket-System',
            ];
            return $map[$key] ?? $default;
        });

        // Ensure SMTP repo returns null by default
        $this->smtpRepo->method('getConfig')->willReturn(null);

        $this->service = new EmailService(
            $this->mailer,
            $this->entityManager,
            $this->userRepo,
            $this->smtpRepo,
            $this->emailSentRepo,
            $this->params,
            __DIR__, // projectDir not used for template path in these tests
            $this->eventDispatcher
        );
    }

    protected function tearDown(): void
    {
        // restore previous error_log setting
        if (null !== $this->prevErrorLog) {
            @ini_set('error_log', $this->prevErrorLog);
        }
    }

    public function testPrepareEmailContentReplacesPlaceholdersAndAddsTestPrefix(): void
    {
        // Prepare template
        $template = "Hallo {{username}},\nTicket: {{ticketId}}\nLink: {{ticketLink}}\nFällig: {{dueDate}}\n{{ticketName}}";
        $ticketData = \App\ValueObject\TicketData::fromStrings('T-123', 'jsmith', 'Problem');

    $user = $this->createMock(\App\Entity\User::class);
    $user->method('getEmail')->willReturn(\App\ValueObject\EmailAddress::fromString('jsmith@example.com'));

        // call private prepareEmailContent via reflection
        $ref = new \ReflectionClass($this->service);
        $method = $ref->getMethod('prepareEmailContent');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $template, $ticketData, $user, 'https://tickets.example', true);

        // Test mode prefix present and contains user's email
        $this->assertStringContainsString('*** TESTMODUS - E-Mail wäre an jsmith@example.com gegangen ***', $result);

        // placeholders replaced
        $this->assertStringNotContainsString('{{ticketId}}', $result);
        $this->assertStringContainsString('T-123', $result);
        $this->assertStringContainsString('https://tickets.example/T-123', $result);
        $this->assertStringContainsString('Problem', $result);

        // due date present (formatted with a month name in German)
        $this->assertMatchesRegularExpression('/\d{1,2}\.\s+\p{L}+\s+\d{4}/u', $result);
    }

    public function testProcessTicketEmailWhenUserNotFound(): void
    {
        $ticketData = \App\ValueObject\TicketData::fromStrings('T-999', 'nouser', 'Missing');

        $this->userRepo->method('findByUsername')->with('nouser')->willReturn(null);

        $ref = new \ReflectionClass($this->service);
        $method = $ref->getMethod('processTicketEmail');
        $method->setAccessible(true);

    /** @var EmailSent $emailSent */
    $emailSent = $method->invoke($this->service, $ticketData, ['subject' => 'subj', 'ticketBaseUrl' => 'https://tickets.example', 'testEmail' => 'test@example.com', 'useCustomSMTP' => false, 'senderEmail' => 'noreply@example.com', 'senderName' => 'Ticket-System'], '', false, new \DateTime());

        $this->assertInstanceOf(EmailSent::class, $emailSent);
        $this->assertEquals(EmailStatus::error('no email found'), $emailSent->getStatus());
        // Wenn kein Benutzer gefunden wird, wird eine Standard-E-Mail-Adresse gesetzt
        $this->assertEquals(\App\ValueObject\EmailAddress::fromString('example@example.com'), $emailSent->getEmail());
    }

    public function testProcessTicketEmailSendsEmailAndMarksSent(): void
    {
        $ticketData = \App\ValueObject\TicketData::fromStrings('T-001', 'user1', 'Demo');

    $user = $this->createMock(\App\Entity\User::class);
    $user->method('getEmail')->willReturn(\App\ValueObject\EmailAddress::fromString('user1@example.com'));

        $this->userRepo->method('findByUsername')->with('user1')->willReturn($user);

        // Ensure SMTP config repository returns null to use default mailer
        $this->smtpRepo->method('getConfig')->willReturn(null);

        // Mailer should be called once
        $this->mailer->expects($this->once())->method('send');

        $ref = new \ReflectionClass($this->service);
        $method = $ref->getMethod('processTicketEmail');
        $method->setAccessible(true);

    /** @var EmailSent $emailSent */
    $emailSent = $method->invoke($this->service, $ticketData, ['subject' => 'subj', 'ticketBaseUrl' => 'https://tickets.example', 'testEmail' => 'test@example.com', 'useCustomSMTP' => false, 'senderEmail' => 'noreply@example.com', 'senderName' => 'Ticket-System'], 'template content', false, new \DateTime());

        $this->assertInstanceOf(EmailSent::class, $emailSent);
        $this->assertEquals(EmailStatus::sent(), $emailSent->getStatus());
        $this->assertStringContainsString('user1@example.com', $emailSent->getEmail());
    }

    public function testProcessTicketEmailWhenMailerThrowsSetsErrorStatus(): void
    {
        $ticketData = \App\ValueObject\TicketData::fromStrings('T-002', 'user2', 'Demo2');

    $user = $this->createMock(\App\Entity\User::class);
    $user->method('getEmail')->willReturn(\App\ValueObject\EmailAddress::fromString('user2@example.com'));

        $this->userRepo->method('findByUsername')->with('user2')->willReturn($user);
        $this->smtpRepo->method('getConfig')->willReturn(null);

        // Mailer throws
        $this->mailer->method('send')->willThrowException(new \Exception('SMTP down'));

        $ref = new \ReflectionClass($this->service);
        $method = $ref->getMethod('processTicketEmail');
        $method->setAccessible(true);

    /** @var EmailSent $emailSent */
    $emailSent = $method->invoke($this->service, $ticketData, ['subject' => 'subj', 'ticketBaseUrl' => 'https://tickets.example', 'testEmail' => 'test@example.com', 'useCustomSMTP' => false, 'senderEmail' => 'noreply@example.com', 'senderName' => 'Ticket-System'], 'template content', false, new \DateTime());

        $this->assertStringStartsWith('Fehler:', $emailSent->getStatus()->getValue());
    }

    public function testSendTicketEmailsWithDuplicateInCsv(): void
    {
        $ticket = TicketData::fromStrings('DUP-001', 'dupuser', 'Dup');
        $tickets = [$ticket, $ticket];

        $user = new \App\Entity\User();
        $user->setEmail('dup@example.com');
        $user->setUsername('dupuser');

        $this->userRepo->method('findByUsername')->willReturn($user);
        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        $this->mailer->expects($this->once())->method('send');
        $this->entityManager->expects($this->atLeast(2))->method('persist');
        $this->entityManager->expects($this->atLeast(2))->method('flush');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);
        $this->assertCount(2, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
        $this->assertStringContainsString('Mehrfach in CSV', $result[1]->getStatus()->getValue());
    }

    public function testSendTicketEmailsWithExistingTicketInDb(): void
    {
        $ticket = TicketData::fromStrings('EXT-001', 'euser', 'Exist');
        $tickets = [$ticket];

        $existing = new \App\Entity\EmailSent();
        $existing->setTimestamp(new \DateTime('2025-01-02'));

        $this->emailSentRepo->method('findExistingTickets')->willReturn(['EXT-001' => $existing]);
        $this->userRepo->method('findByUsername')->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);
        $this->assertCount(1, $result);
        $this->assertStringContainsString('Bereits verarbeitet am', $result[0]->getStatus()->getValue());
        $this->assertStringContainsString('02.01.2025', $result[0]->getStatus()->getValue());
    }

    public function testSendTicketEmailsUserExcludedCreatesSkippedRecord(): void
    {
        $ticket = TicketData::fromStrings('EXC-001', 'ex', 'Ex');
        $tickets = [$ticket];

        $user = new \App\Entity\User();
        $user->setEmail('ex@example.com');
        $user->setUsername('ex');
        $user->setExcludedFromSurveys(true);

        $this->userRepo->method('findByUsername')->willReturn($user);
        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);
        $this->assertCount(1, $result);
        $this->assertStringContainsString('Von Umfragen ausgeschlossen', $result[0]->getStatus());
    }

    public function testGetEmailTemplateFallbackReturnsDefault(): void
    {
        // Use a projectDir that doesn't contain templates
        $svc = new EmailService($this->mailer, $this->entityManager, $this->userRepo, $this->smtpRepo, $this->emailSentRepo, $this->params, sys_get_temp_dir(), $this->eventDispatcher);
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('getEmailTemplate');
        $m->setAccessible(true);

        $tpl = $m->invoke($svc);
        $this->assertStringContainsString('Sehr geehrte', $tpl);
    }

    public function testSendEmailUsesHtmlWhenContentLooksLikeHtml(): void
    {
        $called = false;
        $this->mailer->expects($this->once())->method('send')->with($this->callback(function($email) use (&$called) {
            // Email::getHtmlBody exists and should contain our html
            $called = true;
            return method_exists($email, 'getHtmlBody') && strpos($email->getHtmlBody(), '<p>hi</p>') !== false;
        }));

        $ref = new \ReflectionClass($this->service);
        $m = $ref->getMethod('sendEmail');
        $m->setAccessible(true);

        $config = ['senderEmail' => 's@d', 'senderName' => 'n', 'useCustomSMTP' => false];
        $m->invoke($this->service, 'r@x', 'sub', '<html><p>hi</p></html>', $config);

        $this->assertTrue($called);
    }

    public function testSendTicketEmailsWithForceResendIgnoresExistingTickets(): void
    {
        $ticket = TicketData::fromStrings('FRC-001', 'fuser', 'Force');
        $tickets = [$ticket];

        $existing = new \App\Entity\EmailSent();
        $existing->setTimestamp(new \DateTime('2025-01-02'));

        // existing tickets found but forceResend true => should be ignored
        $this->emailSentRepo->method('findExistingTickets')->willReturn(['F1' => $existing]);

        $user = new \App\Entity\User();
        $user->setEmail('f@example.com');
        $user->setUsername('fuser');
        $this->userRepo->method('findByUsername')->willReturn($user);

        $this->mailer->expects($this->once())->method('send');
        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, true);
        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
    }

    public function testSendTicketEmailsHandlesPersistFlushExceptionCreatesErrorRecord(): void
    {
        $ticket = TicketData::fromStrings('ERR1', 'erruser', 'Err');
        $tickets = [$ticket];

        $user = new \App\Entity\User();
        $user->setEmail('err@example.com');
        $user->setUsername('erruser');
        $this->userRepo->method('findByUsername')->willReturn($user);
        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        // persist will work, first flush will throw to trigger errorRecord path
        $this->entityManager->expects($this->any())->method('persist')->willReturnCallback(function($arg) {
            return null;
        });
        $flushCallCount = 0;
        $this->entityManager->expects($this->any())->method('flush')->willReturnCallback(function() use (&$flushCallCount) {
            $flushCallCount++;
            if ($flushCallCount === 1) {
                throw new \Exception('db save failed');
            }
            return null;
        });

        // Mailer send will be called and succeed
        $this->mailer->expects($this->once())->method('send');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Fehler: database save failed', $result[0]->getStatus()->getValue());
    }

    public function testGetEmailTemplateReadsHtmlFileIfPresent(): void
    {
        $tmpDir = sys_get_temp_dir() . '/email_templates_' . uniqid();
        mkdir($tmpDir . '/templates/emails', 0777, true);
        $htmlPath = $tmpDir . '/templates/emails/email_template.html';
        file_put_contents($htmlPath, '<p>HTML TEMPLATE</p>');

        $svc = new EmailService($this->mailer, $this->entityManager, $this->userRepo, $this->smtpRepo, $this->emailSentRepo, $this->params, $tmpDir, $this->eventDispatcher);
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('getEmailTemplate');
        $m->setAccessible(true);

        $tpl = $m->invoke($svc);
        $this->assertStringContainsString('HTML TEMPLATE', $tpl);

        // cleanup
        unlink($htmlPath);
        rmdir($tmpDir . '/templates/emails');
        rmdir($tmpDir . '/templates');
        rmdir($tmpDir);
    }

    public function testPrepareEmailContentNonTestModeDoesNotPrefix(): void
    {
        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(\App\ValueObject\EmailAddress::fromString('u@example.com'));
        $template = "Hello {{username}} - {{ticketId}} - {{ticketLink}} - {{dueDate}}";

        $ref = new \ReflectionClass($this->service);
        $m = $ref->getMethod('prepareEmailContent');
        $m->setAccessible(true);

        $out = $m->invoke($this->service, $template, \App\ValueObject\TicketData::fromStrings('ZZZ-001','bob','Test Ticket'), $user, 'https://base', false);
        $this->assertStringNotContainsString('*** TESTMODUS', $out);
        $this->assertStringContainsString('ZZZ-001', $out);
    }

    public function testGetEmailConfigurationUsesDbConfig(): void
    {
        // Create a mock SMTPConfig with the getters used by EmailService
        $config = $this->createMock(\App\Entity\SMTPConfig::class);
        $config->method('getSenderEmail')->willReturn(\App\ValueObject\EmailAddress::fromString('dbsender@example.com'));
        $config->method('getSenderName')->willReturn('DB Sender');
        $config->method('getTicketBaseUrl')->willReturn('https://db.example');
        $config->method('getDSN')->willReturn('smtp://u:p@smtp.example:587?encryption=tls&verify_peer=0');

        // Use a fresh SMTPConfigRepository mock to avoid interference from setUp defaults
        $smtpRepo2 = $this->createMock(\App\Repository\SMTPConfigRepository::class);
        $smtpRepo2->method('getConfig')->willReturn($config);

        $svc = new \App\Service\EmailService(
            $this->mailer,
            $this->entityManager,
            $this->userRepo,
            $smtpRepo2,
            $this->emailSentRepo,
            $this->params,
            __DIR__,
            $this->eventDispatcher
        );

        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('getEmailConfiguration');
        $m->setAccessible(true);

        $out = $m->invoke($svc);

    $this->assertIsArray($out);
    // Ensure DB config produced a DSN and applied the ticket base URL
    $this->assertEquals('https://db.example', $out['ticketBaseUrl']);
    $this->assertStringContainsString('smtp://', $out['smtpDSN']);
    }

    public function testCreateSkippedEmailRecordWithAndWithoutUser(): void
    {
        $ticketData = \App\ValueObject\TicketData::fromStrings('SKP-001', 'suser', 'Skip');
        // Case 1: user present
        $user = new \App\Entity\User();
        $user->setEmail('s@example.com');
        $user->setUsername('suser');
        $this->userRepo->method('findByUsername')->with('suser')->willReturn($user);

        $ref = new \ReflectionClass($this->service);
        $m = $ref->getMethod('createSkippedEmailRecord');
        $m->setAccessible(true);

        $now = new \DateTime();
        /** @var \App\Entity\EmailSent $rec */
        $rec = $m->invoke($this->service, $ticketData, $now, false, 'Status text');
        $this->assertInstanceOf(\App\Entity\EmailSent::class, $rec);
        $this->assertEquals(\App\ValueObject\TicketId::fromString('SKP-001'), $rec->getTicketId());
        $this->assertEquals('suser', $rec->getUsername());
        $this->assertEquals(\App\ValueObject\EmailAddress::fromString('s@example.com'), $rec->getEmail());
        $this->assertEquals('Status text', $rec->getStatus());

        // Case 2: user not found -> empty email
        // Use a fresh UserRepository mock and a fresh EmailService to avoid previous stubbing
        $userRepo2 = $this->createMock(\App\Repository\UserRepository::class);
        $userRepo2->method('findByUsername')->willReturn(null);

        $svc2 = new \App\Service\EmailService(
            $this->mailer,
            $this->entityManager,
            $userRepo2,
            $this->smtpRepo,
            $this->emailSentRepo,
            $this->params,
            __DIR__,
            $this->eventDispatcher
        );

        $ref2 = new \ReflectionClass($svc2);
        $m2 = $ref2->getMethod('createSkippedEmailRecord');
        $m2->setAccessible(true);

        $rec2 = $m2->invoke($svc2, $ticketData, $now, true, 'Other');
        $this->assertEquals('', $rec2->getEmail());
        $this->assertEquals('Other', $rec2->getStatus());
        $this->assertTrue($rec2->getTestMode());
    }

    public function testSendTicketEmailsCallsWrapper(): void
    {
        // Ensure wrapper simply calls underlying method; we spy on emailSentRepo findExistingTickets
        $ticket = TicketData::fromStrings('WRP-001', 'wuser', 'Wrap');
        $tickets = [$ticket];

        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);
        $user = new \App\Entity\User();
        $user->setEmail('w@example.com');
        $user->setUsername('wuser');
        $this->userRepo->method('findByUsername')->willReturn($user);

        $this->mailer->expects($this->once())->method('send');

        $result = $this->service->sendTicketEmails($tickets, false);
        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
    }

    public function testSendTicketEmailsWithCustomTestEmail(): void
    {
        $ticket = TicketData::fromStrings('T-123', 'jsmith', 'Problem');
        $tickets = [$ticket];
        $customTestEmail = 'custom-test@example.com';

        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);
        
        $user = new \App\Entity\User();
        $user->setEmail('jsmith@example.com');
        $user->setUsername('jsmith');
        $this->userRepo->method('findByUsername')->willReturn($user);

        // Capture the email sent to verify it uses custom test email
        $sentEmail = null;
        $this->mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function($email) use (&$sentEmail) {
                $sentEmail = $email;
            });

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, true, false, $customTestEmail);
        
        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
        
        // Verify that the custom test email was used
        $this->assertNotNull($sentEmail);
        $recipients = $sentEmail->getTo();
        $this->assertCount(1, $recipients);
        $this->assertEquals($customTestEmail, array_values($recipients)[0]->getAddress());
    }

    public function testSendTicketEmailsUsesDefaultTestEmailWhenCustomIsEmpty(): void
    {
        $ticket = TicketData::fromStrings('T-123', 'jsmith', 'Problem');
        $tickets = [$ticket];

        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);
        
        $user = new \App\Entity\User();
        $user->setEmail('jsmith@example.com');
        $user->setUsername('jsmith');
        $this->userRepo->method('findByUsername')->willReturn($user);

        // Capture the email sent to verify it uses default test email
        $sentEmail = null;
        $this->mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function($email) use (&$sentEmail) {
                $sentEmail = $email;
            });

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, true, false, '');
        
        $this->assertCount(1, $result);
        $this->assertEquals(EmailStatus::sent(), $result[0]->getStatus());
        
        // Verify that the default test email was used (from params)
        $this->assertNotNull($sentEmail);
        $recipients = $sentEmail->getTo();
        $this->assertCount(1, $recipients);
        $this->assertEquals('test@example.com', array_values($recipients)[0]->getAddress());
    }

    public function testSkippedCountCalculationIssue(): void
    {
        // This test demonstrates the bug where skippedCount is calculated by subtraction
        // instead of using the proper isSkipped() method
        
        // Create mock EmailSent objects with different statuses
        $sentEmails = [];
        
        // 1 sent email
        $sentEmail = new \App\Entity\EmailSent();
        $sentEmail->setStatus(EmailStatus::sent());
        $sentEmails[] = $sentEmail;
        
        // 1 error email
        $errorEmail = new \App\Entity\EmailSent();
        $errorEmail->setStatus(EmailStatus::error('Test error'));
        $sentEmails[] = $errorEmail;
        
        // 1 skipped email (already processed)
        $skippedEmail = new \App\Entity\EmailSent();
        $skippedEmail->setStatus(EmailStatus::alreadyProcessed(new \DateTime()));
        $sentEmails[] = $skippedEmail;
        
        // 1 email with custom status that is neither sent, error, nor skipped
        $customEmail = new \App\Entity\EmailSent();
        $customEmail->setStatus(EmailStatus::fromString('Pending Review')); // Custom status
        $sentEmails[] = $customEmail;
        
        // Current buggy calculation: skippedCount = total - sent - failed = 4 - 1 - 1 = 2
        // But the correct count should be 1 (only the "already processed" email is actually skipped)
        
        $sentCount = count(array_filter($sentEmails, fn($email) => $email->getStatus()?->isSent()));
        $failedCount = count(array_filter($sentEmails, fn($email) => $email->getStatus()?->isError()));
        $skippedCountBuggy = count($sentEmails) - $sentCount - $failedCount; // Current buggy calculation
        $skippedCountCorrect = count(array_filter($sentEmails, fn($email) => $email->getStatus()?->isSkipped())); // Correct calculation
        
        $this->assertEquals(1, $sentCount);
        $this->assertEquals(1, $failedCount);
        $this->assertEquals(2, $skippedCountBuggy, 'Buggy calculation incorrectly counts custom status as skipped');
        $this->assertEquals(1, $skippedCountCorrect, 'Correct calculation only counts actually skipped emails');
        
        // Verify the custom status is not actually considered skipped
        $this->assertFalse($customEmail->getStatus()->isSkipped(), 'Custom status should not be considered skipped');
        $this->assertFalse($customEmail->getStatus()->isSent(), 'Custom status should not be considered sent');
        $this->assertFalse($customEmail->getStatus()->isError(), 'Custom status should not be considered error');
    }

    public function testBulkEmailCompletedEventUsesCorrectCounting(): void
    {
        // Integration test to verify that the BulkEmailCompletedEvent gets correct counts
        // when actual EmailService processing creates mixed status emails
        
        $tickets = [
            TicketData::fromStrings('T-001', 'user1', 'Test1'), // will be sent
            TicketData::fromStrings('T-002', 'user2', 'Test2'), // will be skipped (excluded from surveys)
        ];

        $user1 = new \App\Entity\User();
        $user1->setEmail('user1@example.com');
        $user1->setUsername('user1');

        $user2 = new \App\Entity\User();
        $user2->setEmail('user2@example.com');
        $user2->setUsername('user2');
        $user2->setExcludedFromSurveys(true); // This will cause a skip

        $this->userRepo->method('findByUsername')->willReturnCallback(function($username) use ($user1, $user2) {
            return match($username) {
                'user1' => $user1,
                'user2' => $user2,
                default => null
            };
        });

        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);

        // Mailer succeeds for both (user2 is skipped before mailer is called)
        $this->mailer->expects($this->once())->method('send');

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->atLeast(2))->method('flush');

        // Capture the BulkEmailCompletedEvent
        $capturedEvent = null;
        $this->eventDispatcher->method('dispatch')->willReturnCallback(function($event) use (&$capturedEvent) {
            if ($event instanceof \App\Event\Email\BulkEmailCompletedEvent) {
                $capturedEvent = $event;
            }
            return $event;
        });

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);

        $this->assertCount(2, $result);
        
        // Verify the first email was sent and second was skipped
        $this->assertTrue($result[0]->getStatus()->isSent(), 'First email should be sent');
        $this->assertTrue($result[1]->getStatus()->isSkipped(), 'Second email should be skipped');

        // Verify the event was dispatched with correct counts
        $this->assertNotNull($capturedEvent, 'BulkEmailCompletedEvent should have been dispatched');
        $this->assertEquals(1, $capturedEvent->sentCount, 'Event should report 1 sent');
        $this->assertEquals(0, $capturedEvent->failedCount, 'Event should report 0 failed');
        $this->assertEquals(1, $capturedEvent->skippedCount, 'Event should report 1 skipped using proper isSkipped() method');
        
        // Verify total count consistency
        $expectedTotal = $capturedEvent->sentCount + $capturedEvent->failedCount + $capturedEvent->skippedCount;
        $this->assertEquals(count($result), $expectedTotal, 'Total counts should match result count');
    }
}
