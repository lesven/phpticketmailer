<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\CsvUploadController;
use App\Entity\User;
use App\Form\CsvUploadType;
use App\Service\CsvProcessor;
use App\Service\EmailService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CsvUploadControllerTest extends TestCase
{
    public function testUploadRendersFormWhenNotSubmitted()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $csvProcessor = $this->createMock(CsvProcessor::class);
        $emailService = $this->createMock(EmailService::class);

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(false);
    $form->method('createView')->willReturn(new FormView());

        $controller = $this->getMockBuilder(CsvUploadController::class)
            ->setConstructorArgs([$em, $userRepo, $csvProcessor, $emailService])
            ->onlyMethods(['createForm', 'render', 'redirectToRoute'])
            ->getMock();

        $controller->expects($this->once())->method('createForm')->with(CsvUploadType::class)->willReturn($form);
        $controller->expects($this->once())->method('render')->with(
            $this->equalTo('csv_upload/upload.html.twig'),
            $this->callback(function ($arg) {
                return isset($arg['form']) && $arg['form'] instanceof FormView;
            }),
            $this->anything()
        )->willReturn(new Response('ok'));

        $request = Request::create('/upload');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $resp = $controller->upload($request);
        $this->assertInstanceOf(Response::class, $resp);
    }

    public function testUploadRedirectsToUnknownUsersWhenUnknownExists()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $csvProcessor = $this->createMock(CsvProcessor::class);
        $emailService = $this->createMock(EmailService::class);

    $fileField = $this->createMock(FormInterface::class);
        $fileField = $this->createMock(FormInterface::class);
        $fileField->method('getData')->willReturn($this->createMock(UploadedFile::class));
        $testModeField = $this->createMock(FormInterface::class);
        $testModeField->method('getData')->willReturn(false);

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->willReturnMap([
            ['csvFile', $fileField],
            ['testMode', $testModeField],
        ]);

        $csvProcessor->method('process')->willReturn(['unknownUsers' => ['alice'], 'validTickets' => []]);

        $controller = $this->getMockBuilder(CsvUploadController::class)
            ->setConstructorArgs([$em, $userRepo, $csvProcessor, $emailService])
            ->onlyMethods(['createForm', 'render', 'redirectToRoute'])
            ->getMock();

    $controller->method('createForm')->willReturn($form);
    $controller->expects($this->once())->method('redirectToRoute')->with('unknown_users')->willReturn(new RedirectResponse('/unknown'));

        $request = Request::create('/upload', 'POST');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $resp = $controller->upload($request);
        $this->assertInstanceOf(Response::class, $resp);
        $this->assertSame(['alice'], $session->get('unknown_users'));
    }

    public function testUploadRedirectsToSendEmailsWhenAllKnown()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $csvProcessor = $this->createMock(CsvProcessor::class);
        $emailService = $this->createMock(EmailService::class);

    $fileField = $this->createMock(FormInterface::class);
    $fileField->method('getData')->willReturn($this->createMock(UploadedFile::class));
        $testModeField = $this->createMock(FormInterface::class);
        $testModeField->method('getData')->willReturn(true);

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->willReturnMap([
            ['csvFile', $fileField],
            ['testMode', $testModeField],
        ]);

        $csvProcessor->method('process')->willReturn(['unknownUsers' => [], 'validTickets' => [['ticketId' => 'X']]]);

        $controller = $this->getMockBuilder(CsvUploadController::class)
            ->setConstructorArgs([$em, $userRepo, $csvProcessor, $emailService])
            ->onlyMethods(['createForm', 'render', 'redirectToRoute'])
            ->getMock();

    $controller->method('createForm')->willReturn($form);
    $controller->expects($this->once())->method('redirectToRoute')->with('send_emails', ['testMode' => 1])->willReturn(new RedirectResponse('/send'));

        $request = Request::create('/upload', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $resp = $controller->upload($request);
        $this->assertInstanceOf(Response::class, $resp);
    }

    public function testUnknownUsersRedirectsWhenNoUnknowns()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $csvProcessor = $this->createMock(CsvProcessor::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = $this->getMockBuilder(CsvUploadController::class)
            ->setConstructorArgs([$em, $userRepo, $csvProcessor, $emailService])
            ->onlyMethods(['redirectToRoute', 'render'])
            ->getMock();

    $controller->expects($this->once())->method('redirectToRoute')->with('csv_upload')->willReturn(new RedirectResponse('/upload'));

        $request = Request::create('/unknown-users');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $resp = $controller->unknownUsers($request);
        $this->assertInstanceOf(Response::class, $resp);
    }

    public function testUnknownUsersPersistNewUsersOnPost()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $csvProcessor = $this->createMock(CsvProcessor::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = $this->getMockBuilder(CsvUploadController::class)
            ->setConstructorArgs([$em, $userRepo, $csvProcessor, $emailService])
            ->onlyMethods(['redirectToRoute', 'render'])
            ->getMock();

        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $em->expects($this->once())->method('flush');

    $controller->expects($this->once())->method('redirectToRoute')->with('send_emails', ['testMode' => 1])->willReturn(new RedirectResponse('/send'));

        $session = new Session(new MockArraySessionStorage());
        $session->set('unknown_users', ['bob']);

        $request = Request::create('/unknown-users?testMode=1', 'POST');
        $request->setSession($session);
        $request->request->set('email_bob', 'bob@example.com');

        $resp = $controller->unknownUsers($request);
        $this->assertInstanceOf(Response::class, $resp);
    }

    public function testSendEmailsRedirectsWhenNoTickets()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $csvProcessor = $this->createMock(CsvProcessor::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = $this->getMockBuilder(CsvUploadController::class)
            ->setConstructorArgs([$em, $userRepo, $csvProcessor, $emailService])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $controller->expects($this->once())->method('addFlash')->with('error', $this->stringContains('Keine gültigen Tickets'));
    $controller->expects($this->once())->method('redirectToRoute')->with('csv_upload')->willReturn(new RedirectResponse('/upload'));

        $request = Request::create('/send-emails');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $resp = $controller->sendEmails($request);
        $this->assertInstanceOf(Response::class, $resp);
    }

    public function testSendEmailsRendersResultsWhenTicketsPresent()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $csvProcessor = $this->createMock(CsvProcessor::class);
        $emailService = $this->createMock(EmailService::class);

        $sent = [new \App\Entity\EmailSent(), new \App\Entity\EmailSent()];
        $emailService->method('sendTicketEmails')->willReturn($sent);

        $controller = $this->getMockBuilder(CsvUploadController::class)
            ->setConstructorArgs([$em, $userRepo, $csvProcessor, $emailService])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())->method('render')->with(
            $this->equalTo('csv_upload/send_result.html.twig'),
            $this->callback(function ($arg) use ($sent) {
                return isset($arg['sentEmails']) && $arg['sentEmails'] === $sent && array_key_exists('testMode', $arg);
            }),
            $this->anything()
        )->willReturn(new Response('ok'));

        $session = new Session(new MockArraySessionStorage());
        $session->set('valid_tickets', [['ticketId' => 'A']]);

        $request = Request::create('/send-emails?testMode=1', 'GET');
        $request->setSession($session);

        $resp = $controller->sendEmails($request);
        $this->assertInstanceOf(Response::class, $resp);
    }
}
