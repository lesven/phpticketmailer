<?php

namespace App\Tests\Service;

use App\Service\EmailService;
use App\Entity\EmailSent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Repository\SMTPConfigRepository;
use App\Repository\EmailSentRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class EmailServiceTest extends TestCase
{
    private EmailService $service;

    private $mailer;
    private $entityManager;
    private $userRepo;
    private $smtpRepo;
    private $emailSentRepo;
    private $params;
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
            __DIR__ // projectDir not used for template path in these tests
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
        $ticket = ['ticketId' => 'T-123', 'username' => 'jsmith', 'ticketName' => 'Problem'];

    $user = $this->createMock(\App\Entity\User::class);
    $user->method('getEmail')->willReturn(\App\ValueObject\EmailAddress::fromString('jsmith@example.com'));

        // call private prepareEmailContent via reflection
        $ref = new \ReflectionClass($this->service);
        $method = $ref->getMethod('prepareEmailContent');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $template, $ticket, $user, 'https://tickets.example', true);

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
        $ticket = ['ticketId' => 'T-999', 'username' => 'nouser', 'ticketName' => 'Missing'];

        $this->userRepo->method('findByUsername')->with('nouser')->willReturn(null);

        $ref = new \ReflectionClass($this->service);
        $method = $ref->getMethod('processTicketEmail');
        $method->setAccessible(true);

    /** @var EmailSent $emailSent */
    $emailSent = $method->invoke($this->service, $ticket, ['subject' => 'subj', 'ticketBaseUrl' => 'https://tickets.example', 'testEmail' => 'test@example.com', 'useCustomSMTP' => false, 'senderEmail' => 'noreply@example.com', 'senderName' => 'Ticket-System'], '', false, new \DateTime());

        $this->assertInstanceOf(EmailSent::class, $emailSent);
        $this->assertEquals('error: no email found', $emailSent->getStatus());
        $this->assertEquals('', $emailSent->getEmail());
    }

    public function testProcessTicketEmailSendsEmailAndMarksSent(): void
    {
        $ticket = ['ticketId' => 'T-001', 'username' => 'user1', 'ticketName' => 'Demo'];

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
    $emailSent = $method->invoke($this->service, $ticket, ['subject' => 'subj', 'ticketBaseUrl' => 'https://tickets.example', 'testEmail' => 'test@example.com', 'useCustomSMTP' => false, 'senderEmail' => 'noreply@example.com', 'senderName' => 'Ticket-System'], 'template content', false, new \DateTime());

        $this->assertInstanceOf(EmailSent::class, $emailSent);
        $this->assertEquals('sent', $emailSent->getStatus());
        $this->assertStringContainsString('user1@example.com', $emailSent->getEmail());
    }

    public function testProcessTicketEmailWhenMailerThrowsSetsErrorStatus(): void
    {
        $ticket = ['ticketId' => 'T-002', 'username' => 'user2', 'ticketName' => 'Demo2'];

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
    $emailSent = $method->invoke($this->service, $ticket, ['subject' => 'subj', 'ticketBaseUrl' => 'https://tickets.example', 'testEmail' => 'test@example.com', 'useCustomSMTP' => false, 'senderEmail' => 'noreply@example.com', 'senderName' => 'Ticket-System'], 'template content', false, new \DateTime());

        $this->assertStringStartsWith('error:', $emailSent->getStatus());
    }

    public function testSendTicketEmailsWithDuplicateInCsv(): void
    {
        $ticket = ['ticketId' => 'DUP-001', 'username' => 'dupuser', 'ticketName' => 'Dup'];
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
        $this->assertSame('sent', $result[0]->getStatus());
        $this->assertStringContainsString('Mehrfaches Vorkommen', $result[1]->getStatus());
    }

    public function testSendTicketEmailsWithExistingTicketInDb(): void
    {
        $ticket = ['ticketId' => 'EXT-001', 'username' => 'euser', 'ticketName' => 'Exist'];
        $tickets = [$ticket];

        $existing = new \App\Entity\EmailSent();
        $existing->setTimestamp(new \DateTime('2025-01-02'));

        $this->emailSentRepo->method('findExistingTickets')->willReturn(['EXT-001' => $existing]);
        $this->userRepo->method('findByUsername')->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $result = $this->service->sendTicketEmailsWithDuplicateCheck($tickets, false, false);
        $this->assertCount(1, $result);
        $this->assertStringContainsString('bereits verarbeitet', $result[0]->getStatus());
        $this->assertStringContainsString('02.01.2025', $result[0]->getStatus());
    }

    public function testSendTicketEmailsUserExcludedCreatesSkippedRecord(): void
    {
        $ticket = ['ticketId' => 'EXC-001', 'username' => 'ex', 'ticketName' => 'Ex'];
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
        $svc = new EmailService($this->mailer, $this->entityManager, $this->userRepo, $this->smtpRepo, $this->emailSentRepo, $this->params, sys_get_temp_dir());
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
        $ticket = ['ticketId' => 'FRC-001', 'username' => 'fuser', 'ticketName' => 'Force'];
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
        $this->assertSame('sent', $result[0]->getStatus());
    }

    public function testSendTicketEmailsHandlesPersistFlushExceptionCreatesErrorRecord(): void
    {
        $ticket = ['ticketId' => 'ERR1', 'username' => 'erruser', 'ticketName' => 'Err'];
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
        $this->assertStringContainsString('error: database save failed', $result[0]->getStatus());
    }

    public function testGetEmailTemplateReadsHtmlFileIfPresent(): void
    {
        $tmpDir = sys_get_temp_dir() . '/email_templates_' . uniqid();
        mkdir($tmpDir . '/templates/emails', 0777, true);
        $htmlPath = $tmpDir . '/templates/emails/email_template.html';
        file_put_contents($htmlPath, '<p>HTML TEMPLATE</p>');

        $svc = new EmailService($this->mailer, $this->entityManager, $this->userRepo, $this->smtpRepo, $this->emailSentRepo, $this->params, $tmpDir);
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

        $out = $m->invoke($this->service, $template, ['ticketId'=>'ZZZ-001','username'=>'bob'], $user, 'https://base', false);
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
            __DIR__
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
        $ticket = ['ticketId' => 'SKP-001', 'username' => 'suser', 'ticketName' => 'Skip'];
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
        $rec = $m->invoke($this->service, $ticket, $now, false, 'Status text');
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
            __DIR__
        );

        $ref2 = new \ReflectionClass($svc2);
        $m2 = $ref2->getMethod('createSkippedEmailRecord');
        $m2->setAccessible(true);

        $rec2 = $m2->invoke($svc2, $ticket, $now, true, 'Other');
        $this->assertEquals('', $rec2->getEmail());
        $this->assertEquals('Other', $rec2->getStatus());
        $this->assertTrue($rec2->getTestMode());
    }

    public function testSendTicketEmailsCallsWrapper(): void
    {
        // Ensure wrapper simply calls underlying method; we spy on emailSentRepo findExistingTickets
        $ticket = ['ticketId' => 'WRP-001', 'username' => 'wuser', 'ticketName' => 'Wrap'];
        $tickets = [$ticket];

        $this->emailSentRepo->method('findExistingTickets')->willReturn([]);
        $user = new \App\Entity\User();
        $user->setEmail('w@example.com');
        $user->setUsername('wuser');
        $this->userRepo->method('findByUsername')->willReturn($user);

        $this->mailer->expects($this->once())->method('send');

        $result = $this->service->sendTicketEmails($tickets, false);
        $this->assertCount(1, $result);
        $this->assertSame('sent', $result[0]->getStatus());
    }
}
