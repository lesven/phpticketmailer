<?php

namespace App\Tests\Controller;

use App\Controller\DashboardController;
use App\Entity\EmailSent;
use App\Repository\EmailSentRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Tests für den DashboardController
 * 
 * Diese Testklasse überprüft die Funktionalität des Dashboard-Controllers,
 * insbesondere das Laden und Anzeigen der neuesten E-Mail-Aktivitäten.
 */
class DashboardControllerTest extends TestCase
{
    private EmailSentRepository $emailSentRepository;
    private DashboardController $controller;

    protected function setUp(): void
    {
        $this->emailSentRepository = $this->createMock(EmailSentRepository::class);
        $this->controller = new DashboardController($this->emailSentRepository);
    }

    public function testIndexReturnsResponseWithRecentEmails(): void
    {
        // Arrange: Mock-Daten für recent emails erstellen
        $emailSent1 = $this->createEmailSent('TICKET-001', 'user1@example.com', 'sent');
        $emailSent2 = $this->createEmailSent('TICKET-002', 'user2@example.com', 'sent');
        $emailSent3 = $this->createEmailSent('TICKET-003', 'user3@example.com', 'failed');
        
        $recentEmails = [$emailSent1, $emailSent2, $emailSent3];

        // Repository soll die letzten 10 E-Mails in absteigender Reihenfolge zurückgeben
        $this->emailSentRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                [], // criteria
                ['timestamp' => 'DESC'], // orderBy
                10 // limit
            )
            ->willReturn($recentEmails);

        // Twig Environment mocken für das Rendering
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'dashboard/index.html.twig',
                ['recentEmails' => $recentEmails]
            )
            ->willReturn('<html>Dashboard Content</html>');

        // Controller mit Twig konfigurieren (über Reflection, da setContainer protected ist)
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')
            ->with('twig')
            ->willReturn($twig);
        $container->method('has')
            ->with('twig')
            ->willReturn(true);

        $reflection = new \ReflectionClass($this->controller);
        $setContainerMethod = $reflection->getMethod('setContainer');
        $setContainerMethod->setAccessible(true);
        $setContainerMethod->invoke($this->controller, $container);

        // Act: Index-Methode aufrufen
        $response = $this->controller->index();

        // Assert: Response überprüfen
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Dashboard Content</html>', $response->getContent());
    }

    public function testIndexHandlesEmptyEmailList(): void
    {
        // Arrange: Leere E-Mail-Liste
        $recentEmails = [];

        $this->emailSentRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                [],
                ['timestamp' => 'DESC'],
                10
            )
            ->willReturn($recentEmails);

        // Twig Environment mocken
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'dashboard/index.html.twig',
                ['recentEmails' => $recentEmails]
            )
            ->willReturn('<html>Empty Dashboard</html>');

        // Container setup
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')->willReturn($twig);
        $container->method('has')->willReturn(true);

        $reflection = new \ReflectionClass($this->controller);
        $setContainerMethod = $reflection->getMethod('setContainer');
        $setContainerMethod->setAccessible(true);
        $setContainerMethod->invoke($this->controller, $container);

        // Act
        $response = $this->controller->index();

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Empty Dashboard</html>', $response->getContent());
    }

    public function testIndexCallsRepositoryWithCorrectParameters(): void
    {
        // Arrange
        $this->emailSentRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->equalTo([]), // keine Filterkriterien
                $this->equalTo(['timestamp' => 'DESC']), // sortiert nach timestamp DESC
                $this->equalTo(10) // limit 10
            )
            ->willReturn([]);

        // Twig mocken
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('content');

        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')->willReturn($twig);
        $container->method('has')->willReturn(true);

        $reflection = new \ReflectionClass($this->controller);
        $setContainerMethod = $reflection->getMethod('setContainer');
        $setContainerMethod->setAccessible(true);
        $setContainerMethod->invoke($this->controller, $container);

        // Act
        $this->controller->index();

        // Assert: Die Expectations werden automatisch durch PHPUnit überprüft
    }

    /**
     * Hilfsmethode zum Erstellen von EmailSent-Mock-Objekten
     * 
     * @param string $ticketId Die Ticket-ID
     * @param string $email Die E-Mail-Adresse
     * @param string $status Der E-Mail-Status
     * @return EmailSent Mock-Objekt
     */
    private function createEmailSent(string $ticketId, string $email, string $status): EmailSent
    {
        $emailSent = $this->createMock(EmailSent::class);
        
        $emailSent->method('getTicketId')->willReturn($ticketId);
        $emailSent->method('getEmail')->willReturn($email);
        $emailSent->method('getStatus')->willReturn($status);
        $emailSent->method('getTimestamp')->willReturn(new \DateTime());
        
        return $emailSent;
    }
}
