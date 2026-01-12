<?php

namespace App\Tests\Controller;

use App\Controller\DashboardController;
use App\Repository\EmailSentRepository;
use App\Entity\EmailSent;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Environment;

class DashboardControllerTest extends TestCase
{
    private DashboardController $controller;
    private EmailSentRepository $emailSentRepository;
    private \App\Service\StatisticsService $statisticsService;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->emailSentRepository = $this->createMock(EmailSentRepository::class);
        $this->statisticsService = $this->createMock(\App\Service\StatisticsService::class);
        $this->twig = $this->createMock(Environment::class);

        $this->controller = new DashboardController($this->emailSentRepository, $this->statisticsService, $this->createMock(\App\Service\ClockInterface::class));

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

        // Erstelle DTO-Mocks für die Controller-Test
        $monthlyDomainStatistics = [
            new \App\Dto\MonthlyDomainStatistic('2025-08', [new \App\Dto\DomainCount('example.com', 2)], 2),
            new \App\Dto\MonthlyDomainStatistic('2025-09', [new \App\Dto\DomainCount('example.com', 4), new \App\Dto\DomainCount('subsidiary.com', 3)], 7),
            new \App\Dto\MonthlyDomainStatistic('2025-10', [new \App\Dto\DomainCount('example.com', 3)], 3),
            new \App\Dto\MonthlyDomainStatistic('2025-11', [], 0),
            new \App\Dto\MonthlyDomainStatistic('2025-12', [new \App\Dto\DomainCount('subsidiary.com', 5)], 5),
            new \App\Dto\MonthlyDomainStatistic('2026-01', [], 0),
        ];

        $monthlyTicketStatistics = [
            new \App\Dto\MonthlyDomainStatistic('2025-08', [new \App\Dto\DomainCount('example.com', 5)], 5),
            new \App\Dto\MonthlyDomainStatistic('2025-09', [new \App\Dto\DomainCount('example.com', 8), new \App\Dto\DomainCount('subsidiary.com', 6)], 14),
            new \App\Dto\MonthlyDomainStatistic('2025-10', [new \App\Dto\DomainCount('example.com', 7)], 7),
            new \App\Dto\MonthlyDomainStatistic('2025-11', [], 0),
            new \App\Dto\MonthlyDomainStatistic('2025-12', [new \App\Dto\DomainCount('subsidiary.com', 10)], 10),
            new \App\Dto\MonthlyDomainStatistic('2026-01', [], 0),
        ];

        $this->emailSentRepository->method('findBy')
            ->with([], ['timestamp' => 'DESC'], 10)
            ->willReturn($recentEmails);

        $this->emailSentRepository->method('getEmailStatistics')
            ->willReturn($statistics);

        $this->statisticsService->method('getMonthlyUserStatisticsByDomain')
            ->willReturn($monthlyDomainStatistics);

        $this->statisticsService->method('getMonthlyTicketStatisticsByDomain')
            ->willReturn($monthlyTicketStatistics);

        $this->twig->method('render')
            ->with('dashboard/index.html.twig', [
                'recentEmails' => $recentEmails,
                'statistics' => $statistics,
                'monthlyDomainStatistics' => $monthlyDomainStatistics,
                'monthlyTicketStatistics' => $monthlyTicketStatistics,
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
        $emailSent->method('getStatus')->willReturn(EmailStatus::fromString($status));
        $emailSent->method('getEmail')->willReturn(EmailAddress::fromString($email));
        $emailSent->method('getTimestamp')->willReturn(new \DateTime());
        return $emailSent;
    }
}