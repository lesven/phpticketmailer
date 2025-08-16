<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\EmailSent;
use App\Entity\SMTPConfig;
use App\Entity\User;
use App\Service\EmailService;
use App\Repository\SMTPConfigRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;

class EmailServiceTest extends TestCase
{
    public function testProcessTicketEmailNoUserCreatesErrorRecord()
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $params = $this->createMock(ParameterBagInterface::class);

        $projectDir = sys_get_temp_dir();

        $userRepo->method('findByUsername')->willReturn(null);
        $smtpRepo->method('getConfig')->willReturn(null);

        $params->method('get')->willReturnMap([
            ['app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}', 'Ihre Rückmeldung zu Ticket {{ticketId}}'],
            ['app.ticket_base_url', 'https://www.ticket.de', 'https://www.ticket.de'],
            ['app.test_email', 'test@example.com', 'test@example.com'],
            ['app.sender_email', 'noreply@example.com', 'noreply@example.com'],
            ['app.sender_name', 'Ticket-System', 'Ticket-System'],
        ]);

        $service = new EmailService($mailer, $em, $userRepo, $smtpRepo, $params, $projectDir);

        $ref = new \ReflectionClass(EmailService::class);
        $method = $ref->getMethod('processTicketEmail');
        $method->setAccessible(true);

    $ticket = ['ticketId' => 'T123', 'username' => 'nouser', 'ticketName' => 'Problem A'];
    $configMethod = $ref->getMethod('getEmailConfiguration');
    $configMethod->setAccessible(true);
    $config = $configMethod->invoke($service);

    $templateMethod = $ref->getMethod('getEmailTemplate');
    $templateMethod->setAccessible(true);
    $template = $templateMethod->invoke($service);

        /** @var EmailSent $record */
        $record = $method->invoke($service, $ticket, $config, $template, false, new \DateTime());

        $this->assertInstanceOf(EmailSent::class, $record);
        $this->assertSame('error: no email found', $record->getStatus());
        $this->assertSame('', $record->getEmail());
    }

    public function testPrepareEmailContentAddsTestModeHeaderAndReplacesPlaceholders()
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $params = $this->createMock(ParameterBagInterface::class);

        $projectDir = sys_get_temp_dir();

    $user = new User();
    $user->setEmail('user@example.com');

    $userRepo->method('findByUsername')->willReturn($user);
        $smtpRepo->method('getConfig')->willReturn(null);
        $params->method('get')->willReturnMap([
            ['app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}', 'Ihre Rückmeldung zu Ticket {{ticketId}}'],
            ['app.ticket_base_url', 'https://www.ticket.de', 'https://www.ticket.de'],
            ['app.test_email', 'test@example.com', 'test@example.com'],
            ['app.sender_email', 'noreply@example.com', 'noreply@example.com'],
            ['app.sender_name', 'Ticket-System', 'Ticket-System'],
        ]);

        $service = new EmailService($mailer, $em, $userRepo, $smtpRepo, $params, $projectDir);

        $ref = new \ReflectionClass(EmailService::class);
        $method = $ref->getMethod('prepareEmailContent');
        $method->setAccessible(true);

        $template = "Hello {{username}}\nTicket: {{ticketId}} - {{ticketName}}\nLink: {{ticketLink}}";
        $ticket = ['ticketId' => 'T42', 'username' => 'jdoe', 'ticketName' => 'Issue'];

    $content = $method->invoke($service, $template, $ticket, $user, 'https://base.url', true);

        $this->assertStringContainsString('*** TESTMODUS', $content);
        $this->assertStringContainsString('T42', $content);
        $this->assertStringContainsString('Issue', $content);
        $this->assertStringContainsString('https://base.url/T42', $content);
    }

    public function testGetEmailTemplateReturnsDefaultWhenFileMissing()
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $params = $this->createMock(ParameterBagInterface::class);

        $projectDir = sys_get_temp_dir();

        $smtpRepo->method('getConfig')->willReturn(null);
        $params->method('get')->willReturnMap([
            ['app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}', 'Ihre Rückmeldung zu Ticket {{ticketId}}'],
            ['app.ticket_base_url', 'https://www.ticket.de', 'https://www.ticket.de'],
            ['app.test_email', 'test@example.com', 'test@example.com'],
            ['app.sender_email', 'noreply@example.com', 'noreply@example.com'],
            ['app.sender_name', 'Ticket-System', 'Ticket-System'],
        ]);

        $service = new EmailService($mailer, $em, $userRepo, $smtpRepo, $params, $projectDir);

        $ref = new \ReflectionClass(EmailService::class);
        $method = $ref->getMethod('getEmailTemplate');
        $method->setAccessible(true);

        $template = $method->invoke($service);

        $this->assertStringContainsString('Sehr geehrter Kunde', $template);
        $this->assertStringContainsString('{{ticketId}}', $template);
    }

    public function testGetEmailConfigurationUsesSmtpConfigWhenPresent()
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $params = $this->createMock(ParameterBagInterface::class);

        $smtp = $this->createMock(SMTPConfig::class);
        $smtp->method('getSenderEmail')->willReturn('smtp-sender@example.com');
        $smtp->method('getSenderName')->willReturn('SMTP Sender');
        $smtp->method('getDSN')->willReturn('smtp://user:pass@smtp.example');

        $smtpRepo->method('getConfig')->willReturn($smtp);
        $params->method('get')->willReturnMap([
            ['app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}', 'Ihre Rückmeldung zu Ticket {{ticketId}}'],
            ['app.ticket_base_url', 'https://www.ticket.de', 'https://www.ticket.de'],
            ['app.test_email', 'test@example.com', 'test@example.com'],
        ]);

        $service = new EmailService($mailer, $em, $userRepo, $smtpRepo, $params, sys_get_temp_dir());

        $ref = new \ReflectionClass(EmailService::class);
        $method = $ref->getMethod('getEmailConfiguration');
        $method->setAccessible(true);

        $config = $method->invoke($service);

        $this->assertTrue($config['useCustomSMTP']);
        $this->assertSame('smtp-sender@example.com', $config['senderEmail']);
        $this->assertSame('SMTP Sender', $config['senderName']);
        $this->assertArrayHasKey('smtpDSN', $config);
    }

    public function testProcessTicketEmailHappyPathSetsSentStatusAndUsesUserEmail()
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $params = $this->createMock(ParameterBagInterface::class);

        $user = new User();
        $user->setEmail('realuser@example.com');
        $userRepo->method('findByUsername')->willReturn($user);

        $smtpRepo->method('getConfig')->willReturn(null);
        $params->method('get')->willReturnMap([
            ['app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}', 'Ihre Rückmeldung zu Ticket {{ticketId}}'],
            ['app.ticket_base_url', 'https://www.ticket.de', 'https://www.ticket.de'],
            ['app.test_email', 'test@example.com', 'test@example.com'],
            ['app.sender_email', 'noreply@example.com', 'noreply@example.com'],
            ['app.sender_name', 'Ticket-System', 'Ticket-System'],
        ]);

        // Expect mailer->send to be called once
        $mailer->expects($this->once())->method('send');

        $service = new EmailService($mailer, $em, $userRepo, $smtpRepo, $params, sys_get_temp_dir());
        $ref = new \ReflectionClass(EmailService::class);
        $method = $ref->getMethod('processTicketEmail');
        $method->setAccessible(true);

        $ticket = ['ticketId' => 'T7', 'username' => 'jane', 'ticketName' => 'Bug'];
        $configMethod = $ref->getMethod('getEmailConfiguration');
        $configMethod->setAccessible(true);
        $config = $configMethod->invoke($service);

        $templateMethod = $ref->getMethod('getEmailTemplate');
        $templateMethod->setAccessible(true);
        $template = $templateMethod->invoke($service);

        /** @var EmailSent $record */
        $record = $method->invoke($service, $ticket, $config, $template, false, new \DateTime());

        $this->assertSame('sent', $record->getStatus());
        $this->assertSame('realuser@example.com', $record->getEmail());
        $this->assertStringContainsString('T7', $record->getSubject());
    }

    public function testProcessTicketEmailTestModeUsesTestEmail()
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $params = $this->createMock(ParameterBagInterface::class);

        $user = new User();
        $user->setEmail('realuser@example.com');
        $userRepo->method('findByUsername')->willReturn($user);

        $smtpRepo->method('getConfig')->willReturn(null);
        $params->method('get')->willReturnMap([
            ['app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}', 'Ihre Rückmeldung zu Ticket {{ticketId}}'],
            ['app.ticket_base_url', 'https://www.ticket.de', 'https://www.ticket.de'],
            ['app.test_email', 'override@test.example', 'override@test.example'],
            ['app.sender_email', 'noreply@example.com', 'noreply@example.com'],
            ['app.sender_name', 'Ticket-System', 'Ticket-System'],
        ]);

        $mailer->expects($this->once())->method('send');

        $service = new EmailService($mailer, $em, $userRepo, $smtpRepo, $params, sys_get_temp_dir());
        $ref = new \ReflectionClass(EmailService::class);
        $method = $ref->getMethod('processTicketEmail');
        $method->setAccessible(true);

        $ticket = ['ticketId' => 'T9', 'username' => 'joe', 'ticketName' => 'Feature'];
    $config = $ref->getMethod('getEmailConfiguration')->invoke($service);
    // make sure testEmail is present (avoid null from mock mismatch)
    $config['testEmail'] = 'override@test.example';
    $template = $ref->getMethod('getEmailTemplate')->invoke($service);

        /** @var EmailSent $record */
        $record = $method->invoke($service, $ticket, $config, $template, true, new \DateTime());

        $this->assertSame('sent', $record->getStatus());
        $this->assertSame('override@test.example', $record->getEmail());
        $this->assertStringContainsString('T9', $record->getSubject());
    }

    public function testProcessTicketEmailWhenMailerThrowsExceptionSetsErrorStatus()
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $params = $this->createMock(ParameterBagInterface::class);

        $user = new User();
        $user->setEmail('realuser@example.com');
        $userRepo->method('findByUsername')->willReturn($user);

        $smtpRepo->method('getConfig')->willReturn(null);
        $params->method('get')->willReturnMap([
            ['app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}', 'Ihre Rückmeldung zu Ticket {{ticketId}}'],
            ['app.ticket_base_url', 'https://www.ticket.de', 'https://www.ticket.de'],
            ['app.test_email', 'test@example.com', 'test@example.com'],
            ['app.sender_email', 'noreply@example.com', 'noreply@example.com'],
            ['app.sender_name', 'Ticket-System', 'Ticket-System'],
        ]);

        $mailer->method('send')->willThrowException(new \Exception('boom boom'));

        $service = new EmailService($mailer, $em, $userRepo, $smtpRepo, $params, sys_get_temp_dir());
        $ref = new \ReflectionClass(EmailService::class);
        $method = $ref->getMethod('processTicketEmail');
        $method->setAccessible(true);

        $ticket = ['ticketId' => 'T11', 'username' => 'pete', 'ticketName' => 'Crash'];
        $config = $ref->getMethod('getEmailConfiguration')->invoke($service);
        $template = $ref->getMethod('getEmailTemplate')->invoke($service);

        /** @var EmailSent $record */
        $record = $method->invoke($service, $ticket, $config, $template, false, new \DateTime());

        $this->assertStringStartsWith('error: boom boom', $record->getStatus());
    }

    public function testSendTicketEmailsPersistsAllAndFlushes()
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $smtpRepo = $this->createMock(SMTPConfigRepository::class);
        $params = $this->createMock(ParameterBagInterface::class);

        $user1 = new User(); $user1->setEmail('a@example.com');
        $user2 = new User(); $user2->setEmail('b@example.com');
        $userRepo->method('findByUsername')->willReturnOnConsecutiveCalls($user1, $user2);

        $smtpRepo->method('getConfig')->willReturn(null);
        $params->method('get')->willReturnMap([
            ['app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}', 'Ihre Rückmeldung zu Ticket {{ticketId}}'],
            ['app.ticket_base_url', 'https://www.ticket.de', 'https://www.ticket.de'],
            ['app.test_email', 'test@example.com', 'test@example.com'],
            ['app.sender_email', 'noreply@example.com', 'noreply@example.com'],
            ['app.sender_name', 'Ticket-System', 'Ticket-System'],
        ]);

        $mailer->expects($this->exactly(2))->method('send');

        $em->expects($this->exactly(2))->method('persist')->with($this->isInstanceOf(EmailSent::class));
        $em->expects($this->once())->method('flush');

        $service = new EmailService($mailer, $em, $userRepo, $smtpRepo, $params, sys_get_temp_dir());

        $tickets = [
            ['ticketId' => 'A1', 'username' => 'u1', 'ticketName' => 't1'],
            ['ticketId' => 'A2', 'username' => 'u2', 'ticketName' => 't2'],
        ];

        $result = $service->sendTicketEmails($tickets, false);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(EmailSent::class, $result);
    }
}
