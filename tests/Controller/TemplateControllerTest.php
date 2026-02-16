<?php

namespace App\Tests\Controller;

use App\Controller\TemplateController;
use App\Entity\EmailTemplate;
use App\Service\TemplateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class TemplateControllerTest extends TestCase
{
    private TemplateController $controller;
    private TemplateService $templateService;

    protected function setUp(): void
    {
        $this->templateService = $this->createMock(TemplateService::class);

        $this->controller = new TemplateController($this->templateService, '/tmp');

        // Inject mocked services using reflection
        $reflectionClass = new \ReflectionClass($this->controller);
        $containerProperty = $reflectionClass->getParentClass()->getProperty('container');
        $containerProperty->setAccessible(true);

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('render')->willReturn('<html>Template Management</html>');

        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function ($service) use ($twig) {
                return match ($service) {
                    'twig' => $twig,
                    default => null
                };
            });
        $container->method('has')->willReturn(true);

        $containerProperty->setValue($this->controller, $container);
    }

    /**
     * Erstellt einen Container mit RequestStack, Router und Session für Controller-Aktionen,
     * die addFlash() und redirectToRoute() nutzen.
     */
    private function setUpContainerWithSession(): Session
    {
        $session = new Session(new MockArraySessionStorage());

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $router->method('generate')->willReturn('/template/');

        $twig = $this->createMock(\Twig\Environment::class);

        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function ($service) use ($twig, $router, $requestStack) {
                return match ($service) {
                    'twig' => $twig,
                    'router' => $router,
                    'request_stack' => $requestStack,
                    default => null,
                };
            });
        $container->method('has')->willReturn(true);

        $reflectionClass = new \ReflectionClass($this->controller);
        $containerProperty = $reflectionClass->getParentClass()->getProperty('container');
        $containerProperty->setAccessible(true);
        $containerProperty->setValue($this->controller, $container);

        return $session;
    }

    public function testManageShowsTemplateListWithNoSelection(): void
    {
        $this->templateService->method('getAllTemplates')->willReturn([]);

        $request = new Request();
        $response = $this->controller->manage($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testManageShowsSelectedTemplate(): void
    {
        $template = new EmailTemplate();
        $template->setName('Test Template');
        $template->setContent('<p>Hello {{username}}</p>');
        $template->setValidFrom(new \DateTime('2026-01-01'));

        $ref = new \ReflectionClass($template);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($template, 1);

        $this->templateService->method('getAllTemplates')->willReturn([$template]);
        $this->templateService->method('getTemplate')->with(1)->willReturn($template);

        $request = new Request(['templateId' => 1]);
        $response = $this->controller->manage($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCreateRedirectsOnSuccess(): void
    {
        $template = new EmailTemplate();
        $template->setName('New');
        $template->setValidFrom(new \DateTime('2026-06-01'));

        $ref = new \ReflectionClass($template);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($template, 5);

        $this->templateService->method('createTemplate')->willReturn($template);

        $session = $this->setUpContainerWithSession();

        $request = new Request([], [
            'template_name' => 'New',
            'template_valid_from' => '2026-06-01',
        ]);
        $request->setMethod('POST');
        $request->setSession($session);

        $response = $this->controller->create($request);

        $this->assertEquals(302, $response->getStatusCode());
        $flashes = $session->getFlashBag()->peek('success');
        $this->assertNotEmpty($flashes);
    }

    public function testCreateRejectsEmptyName(): void
    {
        $session = $this->setUpContainerWithSession();

        $request = new Request([], [
            'template_name' => '',
            'template_valid_from' => '2026-06-01',
        ]);
        $request->setMethod('POST');
        $request->setSession($session);

        $response = $this->controller->create($request);

        $this->assertEquals(302, $response->getStatusCode());
        $flashes = $session->getFlashBag()->peek('error');
        $this->assertNotEmpty($flashes);
    }

    public function testDeleteRemovesTemplate(): void
    {
        $template = new EmailTemplate();
        $template->setName('ToDelete');

        $ref = new \ReflectionClass($template);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($template, 3);

        $this->templateService->method('getTemplate')->with(3)->willReturn($template);
        $this->templateService->expects($this->once())->method('deleteTemplate')->with($template);

        $session = $this->setUpContainerWithSession();

        $response = $this->controller->delete(3);

        $this->assertEquals(302, $response->getStatusCode());
        $flashes = $session->getFlashBag()->peek('success');
        $this->assertNotEmpty($flashes);
    }
}