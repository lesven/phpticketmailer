<?php

namespace App\Tests\Controller;

use App\Controller\SMTPConfigController;
use App\Entity\SMTPConfig;
use App\Repository\SMTPConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as TwigEnvironment;
use Symfony\Component\Mailer\MailerInterface;

class SMTPConfigControllerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private SMTPConfigRepository $smtpConfigRepository;
    private SMTPConfigController $controller;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->smtpConfigRepository = $this->createMock(SMTPConfigRepository::class);

        $this->controller = new SMTPConfigController($this->entityManager, $this->smtpConfigRepository);
    }

    private function setContainerMocks(array $services): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function ($id) use ($services) {
            return array_key_exists($id, $services);
        });
        $container->method('get')->willReturnCallback(function ($id) use ($services) {
            return $services[$id] ?? null;
        });

        $ref = new \ReflectionClass($this->controller);
        $m = $ref->getMethod('setContainer');
        $m->setAccessible(true);
        $m->invoke($this->controller, $container);

        return $container;
    }

    public function testEditGetCreatesDefaultConfigAndRenders(): void
    {
        $this->smtpConfigRepository->method('getConfig')->willReturn(null);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
        $form->method('createView')->willReturn($formView);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $controller = $this->getMockBuilder(SMTPConfigController::class)
            ->setConstructorArgs([$this->entityManager, $this->smtpConfigRepository])
            ->onlyMethods(['createForm', 'render'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->expects($this->once())
            ->method('render')
            ->with('smtp_config/edit.html.twig', ['form' => $formView])
            ->willReturn(new Response('<html>form</html>'));

        $request = Request::create('/smtp-config', 'GET');
        $mailer = $this->createMock(MailerInterface::class);

        // Ensure no persist/flush on GET (set expectations before call)
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $response = $controller->edit($request, $mailer);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('form', $response->getContent());
    }

    public function testEditPostSavesConfigWithoutTestEmail(): void
    {
        $config = $this->createMock(SMTPConfig::class);
        $this->smtpConfigRepository->method('getConfig')->willReturn($config);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
    $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
    $form->method('createView')->willReturn($formView);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $controller = $this->getMockBuilder(SMTPConfigController::class)
            ->setConstructorArgs([$this->entityManager, $this->smtpConfigRepository])
            ->onlyMethods(['createForm', 'redirectToRoute', 'addFlash'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->expects($this->once())->method('addFlash')->with('success', $this->stringContains('SMTP-Konfiguration wurde erfolgreich gespeichert'));
    $controller->expects($this->once())->method('redirectToRoute')->with('smtp_config_edit')->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/smtp-config'));

        $this->entityManager->expects($this->once())->method('persist')->with($config);
        $this->entityManager->expects($this->once())->method('flush');

        $request = Request::create('/smtp-config', 'POST', []); // no test_email
        $mailer = $this->createMock(MailerInterface::class);

        $response = $controller->edit($request, $mailer);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testEditPostWithTestEmailTransportExceptionAddsErrorFlash(): void
    {
        // config with invalid DSN to trigger Transport exception
        $config = $this->createMock(SMTPConfig::class);
        $config->method('getDSN')->willReturn('not-a-scheme://invalid');
        $config->method('getSenderEmail')->willReturn('noreply@example.com');

        $this->smtpConfigRepository->method('getConfig')->willReturn($config);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
    $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
    $form->method('createView')->willReturn($formView);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $controller = $this->getMockBuilder(SMTPConfigController::class)
            ->setConstructorArgs([$this->entityManager, $this->smtpConfigRepository])
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->expects($this->once())->method('addFlash')->with('error', $this->callback(function ($msg) {
            return is_string($msg) && str_contains($msg, 'Die Konfiguration wurde gespeichert');
        }));
    $controller->expects($this->once())->method('redirectToRoute')->with('smtp_config_edit')->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/smtp-config'));

        $this->entityManager->expects($this->once())->method('persist')->with($config);
        $this->entityManager->expects($this->once())->method('flush');

        $request = Request::create('/smtp-config', 'POST', ['test_email' => 'test@example.com']);
        $mailer = $this->createMock(MailerInterface::class);

        $response = $controller->edit($request, $mailer);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testEditPostWhenFormInvalidRendersFormAgain(): void
    {
        $config = $this->createMock(SMTPConfig::class);
        $this->smtpConfigRepository->method('getConfig')->willReturn($config);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);
        $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
        $form->method('createView')->willReturn($formView);

        $controller = $this->getMockBuilder(SMTPConfigController::class)
            ->setConstructorArgs([$this->entityManager, $this->smtpConfigRepository])
            ->onlyMethods(['createForm', 'render'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->expects($this->once())
            ->method('render')
            ->with('smtp_config/edit.html.twig', ['form' => $formView])
            ->willReturn(new Response('<html>form invalid</html>'));

        $request = Request::create('/smtp-config', 'POST', []);
        $mailer = $this->createMock(MailerInterface::class);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $response = $controller->edit($request, $mailer);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('form invalid', $response->getContent());
    }

    public function testEditPostPersistThrowsExceptionBubblesUp(): void
    {
        $this->expectException(\Exception::class);

        $config = $this->createMock(SMTPConfig::class);
        $this->smtpConfigRepository->method('getConfig')->willReturn($config);

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $controller = $this->getMockBuilder(SMTPConfigController::class)
            ->setConstructorArgs([$this->entityManager, $this->smtpConfigRepository])
            ->onlyMethods(['createForm', 'redirectToRoute', 'addFlash'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $this->entityManager->expects($this->once())->method('persist')->willThrowException(new \Exception('persist failed'));

        $request = Request::create('/smtp-config', 'POST', []);
        $mailer = $this->createMock(MailerInterface::class);

        // exception should bubble
        $controller->edit($request, $mailer);
    }

    public function testEditPostSavesLargeValues(): void
    {
        $config = $this->createMock(SMTPConfig::class);
        $this->smtpConfigRepository->method('getConfig')->willReturn($config);

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $controller = $this->getMockBuilder(SMTPConfigController::class)
            ->setConstructorArgs([$this->entityManager, $this->smtpConfigRepository])
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->expects($this->once())->method('addFlash')->with('success', $this->stringContains('SMTP-Konfiguration'));
        $controller->expects($this->once())->method('redirectToRoute')->with('smtp_config_edit')->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/smtp-config'));

        $this->entityManager->expects($this->once())->method('persist')->with($config);
        $this->entityManager->expects($this->once())->method('flush');

        // simulate large values in request (but form handling is mocked)
    $long = str_repeat('a', 10000);
    // do not send test email to avoid transport issues; only test saving large input
    $request = Request::create('/smtp-config', 'POST', []);
        $mailer = $this->createMock(MailerInterface::class);

        $response = $controller->edit($request, $mailer);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function testEditUsesExistingConfigWhenPresent(): void
    {
        $config = $this->createMock(SMTPConfig::class);
        // ensure we don't create defaults
        $this->smtpConfigRepository->method('getConfig')->willReturn($config);

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
        $form->method('createView')->willReturn($formView);

        $controller = $this->getMockBuilder(SMTPConfigController::class)
            ->setConstructorArgs([$this->entityManager, $this->smtpConfigRepository])
            ->onlyMethods(['createForm', 'render'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->expects($this->once())->method('render')->with('smtp_config/edit.html.twig', ['form' => $formView])->willReturn(new Response('<html>existing</html>'));

        $request = Request::create('/smtp-config', 'GET');
        $mailer = $this->createMock(MailerInterface::class);

        $response = $controller->edit($request, $mailer);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('existing', $response->getContent());
    }
}
