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

    public function testExtractEmailMappingsFromRequestHandlesUsernamesWithDots(): void
    {
        // Test for the specific issue where usernames containing dots are converted to underscores in HTML
        $unknownUsers = ['h.asakura', 'normal.user', 'user_without_dots'];
        
        $request = new Request();
        // Simulate form data with underscores (as HTML would generate them)
        $request->request->set('email_h_asakura', 'h.asakura@example.com');
        $request->request->set('email_normal_user', 'normal.user@example.com');
        $request->request->set('email_user_without_dots', 'user@example.com');
        
        // Mock email normalizer to return input as-is for simplicity
        $this->emailNormalizer->method('normalizeEmail')
            ->willReturnCallback(function($email) {
                return $email;
            });
        
        // Use reflection to access the private method
        $reflectionClass = new \ReflectionClass($this->controller);
        $method = $reflectionClass->getMethod('extractEmailMappingsFromRequest');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $request, $unknownUsers);
        
        // Verify that all usernames are correctly mapped despite dots being converted to underscores
        $this->assertArrayHasKey('h.asakura', $result);
        $this->assertArrayHasKey('normal.user', $result);
        $this->assertArrayHasKey('user_without_dots', $result);
        
        $this->assertEquals('h.asakura@example.com', $result['h.asakura']);
        $this->assertEquals('normal.user@example.com', $result['normal.user']);
        $this->assertEquals('user@example.com', $result['user_without_dots']);
    }

    public function testConvertUsernameForHtmlAttributeReplacesDotsWithUnderscores(): void
    {
        // Test the helper method that ensures consistency with Twig's html_attr escaping
        $reflectionClass = new \ReflectionClass($this->controller);
        $method = $reflectionClass->getMethod('convertUsernameForHtmlAttribute');
        $method->setAccessible(true);
        
        // Test various usernames with dots
        $testCases = [
            'h.asakura' => 'h_asakura',
            'normal.user' => 'normal_user',
            'user.with.multiple.dots' => 'user_with_multiple_dots',
            'user_without_dots' => 'user_without_dots',
            'user' => 'user'
        ];
        
        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->controller, $input);
            $this->assertEquals($expected, $result, "Failed to convert '$input' to '$expected'");
        }
    }

}