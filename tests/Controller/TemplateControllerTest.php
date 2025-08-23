<?php

namespace App\Tests\Controller;

use App\Controller\TemplateController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Symfony\Component\String\Slugger\SluggerInterface;

class TemplateControllerTest extends TestCase
{
    private TemplateController $controller;
    private FormFactoryInterface $formFactory;
    private Environment $twig;
    private \Symfony\Component\String\Slugger\SluggerInterface $slugger;

    protected function setUp(): void
    {
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->slugger = $this->createMock(\Symfony\Component\String\Slugger\SluggerInterface::class);

        $this->controller = new TemplateController('/tmp', $this->slugger);

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

    public function testManageShowsTemplateManagementForm(): void
    {
        $request = new Request();
        
        $form = $this->createMock(FormInterface::class);
        $formView = $this->createMock(FormView::class);
        
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn($formView);

        $this->formFactory->method('create')
            ->willReturn($form);

        $this->twig->method('render')
            ->with('template/manage.html.twig', $this->anything())
            ->willReturn('<html>Template Management</html>');

        $response = $this->controller->manage($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('<html>', $response->getContent());
        $this->assertEquals('<html>Template Management</html>', $response->getContent());
    }

    public function testManageUpdatesTemplateOnValidSubmission(): void
    {
        $request = new Request();
        $templateContent = 'Hello {{username}}, your ticket {{ticketId}} is ready.';
        
        $form = $this->createMock(FormInterface::class);
        $templateField = $this->createMock(FormInterface::class);
        
        $form->method('get')->with('template')->willReturn($templateField);
        $templateField->method('getData')->willReturn($templateContent);
        
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $this->formFactory->method('create')
            ->willReturn($form);

        $formView = $this->createMock(FormView::class);
        $form->method('createView')->willReturn($formView);

        $this->twig->method('render')
            ->with('template/manage.html.twig', $this->anything())
            ->willReturn('<html>Template Updated</html>');

        $response = $this->controller->manage($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('<html>', $response->getContent());
        $this->assertEquals('<html>Template Updated</html>', $response->getContent());
    }

    public function testManageHandlesInvalidTemplateSubmission(): void
    {
        $request = new Request();
        
        $form = $this->createMock(FormInterface::class);
        $formView = $this->createMock(FormView::class);
        
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);
        $form->method('createView')->willReturn($formView);

        $this->formFactory->method('create')
            ->willReturn($form);

        $this->twig->method('render')
            ->with('template/manage.html.twig', $this->anything())
            ->willReturn('<html>Template Form with Errors</html>');

        $response = $this->controller->manage($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('<html>', $response->getContent());
        $this->assertEquals('<html>Template Form with Errors</html>', $response->getContent());
    }
}