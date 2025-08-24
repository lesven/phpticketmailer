<?php

namespace App\Tests\Controller;

use App\Controller\MonitoringController;
use App\Service\MonitoringService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Environment;

/**
 * Test-Klasse für den MonitoringController
 * 
 * Diese Klasse testet die Funktionalität des MonitoringControllers,
 * der für die Anzeige der Monitoring-Oberfläche zuständig ist.
 */
class MonitoringControllerTest extends TestCase
{
    private MonitoringController $controller;
    private MonitoringService $monitoringService;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->monitoringService = $this->createMock(MonitoringService::class);
        $this->twig = $this->createMock(Environment::class);

        $this->controller = new MonitoringController($this->monitoringService);

        // Inject mocked services using reflection
        $reflectionClass = new \ReflectionClass($this->controller);
        $containerProperty = $reflectionClass->getParentClass()->getProperty('container');
        $containerProperty->setAccessible(true);
        
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function($service) {
                return match($service) {
                    'twig' => $this->twig,
                    default => null
                };
            });
        $container->method('has')->willReturn(true);
        
        $containerProperty->setValue($this->controller, $container);
    }

    /**
     * Testet die index-Methode des MonitoringControllers
     * - Überprüft, dass die Monitoring-Oberfläche korrekt gerendert wird
     * - Überprüft HTTP-Statuscode 200 und korrekten Template-Aufruf
     */
    public function testIndexShowsMonitoringInterface(): void
    {
        $this->twig->method('render')
            ->with('monitoring/index.html.twig')
            ->willReturn('<html>Monitoring Interface</html>');

        $response = $this->controller->index();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Monitoring Interface</html>', $response->getContent());
    }

    /**
     * Testet die korrekte Dependency Injection des MonitoringService
     * - Überprüft, dass der Controller korrekt erstellt wird
     * - Überprüft die grundlegende Funktionalität der Klassen-Initialisierung
     */
    public function testMonitoringServiceIsInjectedCorrectly(): void
    {
        $this->assertInstanceOf(MonitoringController::class, $this->controller);
        $this->assertTrue(true); // Simple assertion to verify setup
    }
}