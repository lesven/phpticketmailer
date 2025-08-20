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

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $this->emailSentRepo = $this->createMock(EmailSentRepository::class);
        $this->params = $this->createMock(ParameterBagInterface::class);

        // sensible defaults for parameters used in EmailService
        $this->params->method('get')->willReturnMap([
            ['app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}', 'Ihre Rückmeldung zu Ticket {{ticketId}}'],
            ['app.ticket_base_url', 'https://tickets.example', 'https://tickets.example'],
            ['app.test_email', 'test@example.com', 'test@example.com'],
            ['app.sender_email', 'noreply@example.com', 'noreply@example.com'],
            ['app.sender_name', 'Ticket-System', 'Ticket-System'],
        ]);

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

    public function testPrepareEmailContentReplacesPlaceholdersAndAddsTestPrefix(): void
    {
        // Prepare template
        $template = "Hallo {{username}},\nTicket: {{ticketId}}\nLink: {{ticketLink}}\nFällig: {{dueDate}}\n{{ticketName}}";
        $ticket = ['ticketId' => 'T-123', 'username' => 'jsmith', 'ticketName' => 'Problem'];

    $user = $this->createMock(\App\Entity\User::class);
    $user->method('getEmail')->willReturn('jsmith@example.com');

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
        $ticket = ['ticketId' => 'T-1', 'username' => 'user1', 'ticketName' => 'Demo'];

    $user = $this->createMock(\App\Entity\User::class);
    $user->method('getEmail')->willReturn('user1@example.com');

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
        $ticket = ['ticketId' => 'T-2', 'username' => 'user2', 'ticketName' => 'Demo2'];

    $user = $this->createMock(\App\Entity\User::class);
    $user->method('getEmail')->willReturn('user2@example.com');

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
}
