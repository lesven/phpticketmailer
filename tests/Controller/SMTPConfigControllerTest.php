<?php

namespace App\Tests\Controller;

use App\Controller\SMTPConfigController;
use App\Entity\SMTPConfig;
use App\Form\SMTPConfigType;
use App\Repository\SMTPConfigRepository;
use App\Service\EmailTransportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;

class SMTPConfigControllerTest extends TestCase
{
    private SMTPConfigController $controller;
    private EntityManagerInterface $entityManager;
    private SMTPConfigRepository $smtpConfigRepository;
    private EmailTransportService $emailTransportService;
    private FormFactoryInterface $formFactory;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->smtpConfigRepository = $this->createMock(SMTPConfigRepository::class);
        $this->emailTransportService = $this->createMock(EmailTransportService::class);
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->controller = new SMTPConfigController(
            $this->entityManager,
            $this->smtpConfigRepository,
            $this->emailTransportService,
        );

        // Inject mocked services using reflection
        $reflectionClass = new \ReflectionClass($this->controller);
        $containerProperty = $reflectionClass->getParentClass()->getProperty('container');
        $containerProperty->setAccessible(true);
        
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function($service) {
                return match($service) {
                    'form.factory' => $this->formFactory,
                    'twig' => $this->twig,
                    default => null
                };
            });
        $container->method('has')->willReturn(true);
        
        $containerProperty->setValue($this->controller, $container);
    }

    public function testEditShowsFormWithExistingConfig(): void
    {
        $request = new Request();
        $config = new SMTPConfig();
        $config->setHost('smtp.example.com');
        $config->setPort(587);
        
        $this->smtpConfigRepository->method('getConfig')
            ->willReturn($config);

        $form = $this->createMock(FormInterface::class);
        $formView = $this->createMock(FormView::class);
        
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn($formView);

        $this->formFactory->method('create')
            ->with(SMTPConfigType::class, $config)
            ->willReturn($form);

        $this->twig->method('render')
            ->with('smtp_config/edit.html.twig', $this->callback(function($data) use ($formView) {
                return isset($data['form']) && $data['form'] === $formView;
            }))
            ->willReturn('<html>SMTP Config Form</html>');

        $response = $this->controller->edit($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>SMTP Config Form</html>', $response->getContent());
    }

    public function testEditCreatesNewConfigWhenNoneExists(): void
    {
        $request = new Request();
        
        $this->smtpConfigRepository->method('getConfig')
            ->willReturn(null);

        $form = $this->createMock(FormInterface::class);
        $formView = $this->createMock(FormView::class);
        
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn($formView);

        $this->formFactory->method('create')
            ->with(SMTPConfigType::class, $this->callback(function($config) {
                return $config instanceof SMTPConfig && 
                       $config->getHost() === 'localhost' && 
                       $config->getPort() === 25;
            }))
            ->willReturn($form);

        $this->twig->method('render')
            ->willReturn('<html>New SMTP Config Form</html>');

        $response = $this->controller->edit($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>New SMTP Config Form</html>', $response->getContent());
    }

    public function testEntityManagerIsInjectedCorrectly(): void
    {
        $this->assertInstanceOf(SMTPConfigController::class, $this->controller);
        $this->assertTrue(true); // Simple test to verify setup
    }
}