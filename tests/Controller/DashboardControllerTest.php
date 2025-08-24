<?php

namespace App\Tests\Controller;

use App\Controller\DashboardController;
use App\Repository\EmailSentRepository;
use App\Entity\EmailSent;
use App\ValueObject\EmailAddress;
use App\ValueObject\TicketId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Environment;

class DashboardControllerTest extends TestCase
{
    private DashboardController $controller;
    private EmailSentRepository $emailSentRepository;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->emailSentRepository = $this->createMock(EmailSentRepository::class);
        $this->twig = $this->createMock(Environment::class);

        $this->controller = new DashboardController($this->emailSentRepository);

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

    public function testIndexDisplaysDashboardWithRecentEmails(): void
    {
        $recentEmails = [
            $this->createMockEmailSent('T-001', 'sent', 'user1@example.com'),
            $this->createMockEmailSent('T-002', 'sent', 'user2@example.com'),
            $this->createMockEmailSent('T-003', 'error: SMTP failed', 'user3@example.com')
        ];

        $statistics = [
            'totalEmails' => 150,
            'sentToday' => 25,
            'errorsToday' => 2
        ];

        $this->emailSentRepository->method('findBy')
            ->with([], ['timestamp' => 'DESC'], 10)
            ->willReturn($recentEmails);

        $this->emailSentRepository->method('getEmailStatistics')
            ->willReturn($statistics);

        $this->twig->method('render')
            ->with('dashboard/index.html.twig', [
                'recentEmails' => $recentEmails,
                'statistics' => $statistics
            ])
            ->willReturn('<html>Dashboard</html>');

        $response = $this->controller->index();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Dashboard</html>', $response->getContent());
    }

    public function testStatisticsReturnsJsonResponse(): void
    {
        $statistics = [
            'totalEmails' => 100,
            'sentToday' => 15,
            'errorsToday' => 1
        ];

        $this->emailSentRepository->method('getEmailStatistics')
            ->willReturn($statistics);

        $response = $this->controller->statistics();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(100, $data['totalEmails']);
        $this->assertEquals(15, $data['sentToday']);
        $this->assertEquals(1, $data['errorsToday']);
    }

    private function createMockEmailSent(string $ticketId, string $status, string $email): EmailSent
    {
        $emailSent = $this->createMock(EmailSent::class);
        $emailSent->method('getTicketId')->willReturn(TicketId::fromString($ticketId));
        $emailSent->method('getStatus')->willReturn($status);
        $emailSent->method('getEmail')->willReturn(EmailAddress::fromString($email));
        $emailSent->method('getTimestamp')->willReturn(new \DateTime());
        return $emailSent;
    }
}