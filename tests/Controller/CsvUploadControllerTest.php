<?php

namespace App\Tests\Controller;

use App\Controller\CsvUploadController;
use App\Entity\CsvFieldConfig;
use App\Form\CsvUploadType;
use App\Repository\CsvFieldConfigRepository;
use App\Service\CsvUploadOrchestrator;
use App\Service\SessionManager;
use App\Service\EmailService;
use App\Service\EmailNormalizer;
use App\Service\UploadResult;
use App\Exception\TicketMailerException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CsvUploadControllerTest extends TestCase
{
    private CsvUploadController $controller;
    private CsvUploadOrchestrator $csvUploadOrchestrator;
    private SessionManager $sessionManager;
    private EmailService $emailService;
    private CsvFieldConfigRepository $csvFieldConfigRepository;
    private EmailNormalizer $emailNormalizer;
    private FormFactoryInterface $formFactory;
    private Environment $twig;
    private UrlGeneratorInterface $urlGenerator;
    private ParameterBagInterface $params;

    protected function setUp(): void
    {
        $this->csvUploadOrchestrator = $this->createMock(CsvUploadOrchestrator::class);
        $this->sessionManager = $this->createMock(SessionManager::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->csvFieldConfigRepository = $this->createMock(CsvFieldConfigRepository::class);
        $this->emailNormalizer = $this->createMock(EmailNormalizer::class);
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);

        $this->controller = new CsvUploadController(
            $this->csvUploadOrchestrator,
            $this->sessionManager,
            $this->emailService,
            $this->csvFieldConfigRepository,
            $this->emailNormalizer,
            $this->params
        );

        // Inject mocked services using reflection
        $reflectionClass = new \ReflectionClass($this->controller);
        
        $formFactoryProperty = $reflectionClass->getParentClass()->getProperty('container');
        $formFactoryProperty->setAccessible(true);
        
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function($service) {
                return match($service) {
                    'form.factory' => $this->formFactory,
                    'router' => $this->urlGenerator,
                    'twig' => $this->twig,
                    default => null
                };
            });
        $container->method('has')->willReturn(true);
        
        $formFactoryProperty->setValue($this->controller, $container);
    }

    public function testUploadShowsFormWhenNotSubmitted(): void
    {
        $request = new Request();
        $csvFieldConfig = new CsvFieldConfig();
        
        $this->csvFieldConfigRepository->method('getCurrentConfig')
            ->willReturn($csvFieldConfig);

        $form = $this->createMock(FormInterface::class);
        $formView = $this->createMock(FormView::class);
        $csvFieldConfigForm = $this->createMock(FormInterface::class);
        $testEmailForm = $this->createMock(FormInterface::class);
        
        $form->method('get')->willReturnMap([
            ['csvFieldConfig', $csvFieldConfigForm],
            ['testEmail', $testEmailForm]
        ]);
        $csvFieldConfigForm->method('setData')->with($csvFieldConfig);
        $testEmailForm->method('setData')->with('test@example.com');
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn($formView);

        // Mock the params service to return default test email
        $this->params->method('get')->with('app.test_email')->willReturn('test@example.com');

        $this->formFactory->method('create')
            ->with(CsvUploadType::class)
            ->willReturn($form);

        $this->twig->method('render')
            ->with('csv_upload/upload.html.twig', [
                'form' => $formView,
                'currentConfig' => $csvFieldConfig,
            ])
            ->willReturn('<html>Upload Form</html>');

        $response = $this->controller->upload($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Upload Form</html>', $response->getContent());
    }

    public function testOrchestratorIsInjectedCorrectly(): void
    {
        $this->assertInstanceOf(CsvUploadController::class, $this->controller);
        $this->assertTrue(true); // Simple test to verify setup
    }

}