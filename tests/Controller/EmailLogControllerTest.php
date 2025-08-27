<?php

namespace App\Tests\Controller;

use App\Controller\EmailLogController;
use App\Repository\EmailSentRepository;
use App\Service\PaginationService;
use App\Service\PaginationResult;
use App\Entity\EmailSent;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketId;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class EmailLogControllerTest extends TestCase
{
    private EmailLogController $controller;
    private EmailSentRepository $emailSentRepository;
    private PaginationService $paginationService;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->emailSentRepository = $this->createMock(EmailSentRepository::class);
        $this->paginationService = $this->createMock(PaginationService::class);
        $this->twig = $this->createMock(Environment::class);

        $this->controller = new EmailLogController($this->emailSentRepository);

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

    public function testIndexWithoutSearchShowsPaginatedResults(): void
    {
        $request = new Request(['filter' => 'sent', 'page' => '2']);
        
        $emails = [
            $this->createMockEmailSent('T-001', 'sent'),
            $this->createMockEmailSent('T-002', 'sent')
        ];
        
        $queryBuilder = $this->createMock(QueryBuilder::class);
        
        $this->emailSentRepository->method('createFilteredQueryBuilder')
            ->with('sent')
            ->willReturn($queryBuilder);

        $paginationResult = new PaginationResult(
            results: $emails, 
            currentPage: 2, 
            totalPages: 5,
            totalItems: 100,
            itemsPerPage: 50,
            hasNext: true,
            hasPrevious: true
        );
        
        $this->paginationService->method('paginate')
            ->with($queryBuilder, 2, 50)
            ->willReturn($paginationResult);

        $this->twig->method('render')
            ->with('email_log/index.html.twig', [
                'emails' => $emails,
                'search' => null,
                'filter' => 'sent',
                'pagination' => $paginationResult,
                'currentPage' => 2,
                'totalPages' => 5,
            ])
            ->willReturn('<html>Email Log</html>');

        $response = $this->controller->index($request, $this->paginationService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Email Log</html>', $response->getContent());
    }

    public function testIndexWithSearchShowsAllSearchResults(): void
    {
        $request = new Request(['search' => 'T-123', 'filter' => 'all']);
        
        $emails = [
            $this->createMockEmailSent('T-1234', 'sent'),
            $this->createMockEmailSent('T-12345', 'error')
        ];
        
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);
        
        $this->emailSentRepository->method('createFilteredQueryBuilder')
            ->with('all')
            ->willReturn($queryBuilder);

        // Mock the search query building
        $queryBuilder->method('andWhere')
            ->with('e.ticketId LIKE :search')
            ->willReturn($queryBuilder);
        
        $queryBuilder->method('setParameter')
            ->with('search', '%T-123%')
            ->willReturn($queryBuilder);

        $queryBuilder->method('getQuery')
            ->willReturn($query);
        
        $query->method('getResult')
            ->willReturn($emails);

        $this->twig->method('render')
            ->with('email_log/index.html.twig', [
                'emails' => $emails,
                'search' => 'T-123',
                'filter' => 'all',
                'pagination' => null,
                'currentPage' => 1,
                'totalPages' => 1,
            ])
            ->willReturn('<html>Search Results</html>');

        $response = $this->controller->index($request, $this->paginationService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Search Results</html>', $response->getContent());
    }

    public function testIndexWithDefaultParameters(): void
    {
        $request = new Request();
        
        $emails = [
            $this->createMockEmailSent('T-001', 'sent')
        ];
        
        $queryBuilder = $this->createMock(QueryBuilder::class);
        
        $this->emailSentRepository->method('createFilteredQueryBuilder')
            ->with('all') // Default filter
            ->willReturn($queryBuilder);

        $paginationResult = new PaginationResult(
            results: $emails, 
            currentPage: 1, // Default page
            totalPages: 1,
            totalItems: 1,
            itemsPerPage: 50,
            hasNext: false,
            hasPrevious: false
        );
        
        $this->paginationService->method('paginate')
            ->with($queryBuilder, 1, 50)
            ->willReturn($paginationResult);

        $this->twig->method('render')
            ->with('email_log/index.html.twig', [
                'emails' => $emails,
                'search' => null,
                'filter' => 'all',
                'pagination' => $paginationResult,
                'currentPage' => 1,
                'totalPages' => 1,
            ])
            ->willReturn('<html>Default Email Log</html>');

        $response = $this->controller->index($request, $this->paginationService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Default Email Log</html>', $response->getContent());
    }

    public function testIndexWithInvalidPageDefaultsToPageOne(): void
    {
        $request = new Request(['page' => '-3']);
        
        $emails = [
            $this->createMockEmailSent('T-001', 'sent')
        ];
        
        $queryBuilder = $this->createMock(QueryBuilder::class);
        
        $this->emailSentRepository->method('createFilteredQueryBuilder')
            ->with('all')
            ->willReturn($queryBuilder);

        $paginationResult = new PaginationResult(
            results: $emails, 
            currentPage: 1,
            totalPages: 1,
            totalItems: 1,
            itemsPerPage: 50,
            hasNext: false,
            hasPrevious: false
        );
        
        $this->paginationService->method('paginate')
            ->with($queryBuilder, 1, 50) // Should be 1, not -3
            ->willReturn($paginationResult);

        $this->twig->method('render')->willReturn('<html>Email Log</html>');

        $response = $this->controller->index($request, $this->paginationService);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexWithErrorFilter(): void
    {
        $request = new Request(['filter' => 'error']);
        
        $emails = [
            $this->createMockEmailSent('T-001', 'error: SMTP failed'),
            $this->createMockEmailSent('T-002', 'error: User not found')
        ];
        
        $queryBuilder = $this->createMock(QueryBuilder::class);
        
        $this->emailSentRepository->method('createFilteredQueryBuilder')
            ->with('error')
            ->willReturn($queryBuilder);

        $paginationResult = new PaginationResult(
            results: $emails, 
            currentPage: 1,
            totalPages: 1,
            totalItems: 2,
            itemsPerPage: 50,
            hasNext: false,
            hasPrevious: false
        );
        
        $this->paginationService->method('paginate')
            ->with($queryBuilder, 1, 50)
            ->willReturn($paginationResult);

        $this->twig->method('render')
            ->with('email_log/index.html.twig', [
                'emails' => $emails,
                'search' => null,
                'filter' => 'error',
                'pagination' => $paginationResult,
                'currentPage' => 1,
                'totalPages' => 1,
            ])
            ->willReturn('<html>Error Emails</html>');

        $response = $this->controller->index($request, $this->paginationService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Error Emails</html>', $response->getContent());
    }

    public function testIndexWithEmptySearchString(): void
    {
        $request = new Request(['search' => '']);
        
        $emails = [
            $this->createMockEmailSent('T-001', 'sent')
        ];
        
        $queryBuilder = $this->createMock(QueryBuilder::class);
        
        $this->emailSentRepository->method('createFilteredQueryBuilder')
            ->with('all')
            ->willReturn($queryBuilder);

        $paginationResult = new PaginationResult(
            results: $emails, 
            currentPage: 1,
            totalPages: 1,
            totalItems: 1,
            itemsPerPage: 50,
            hasNext: false,
            hasPrevious: false
        );
        
        $this->paginationService->method('paginate')
            ->with($queryBuilder, 1, 50)
            ->willReturn($paginationResult);

        $this->twig->method('render')
            ->with('email_log/index.html.twig', [
                'emails' => $emails,
                'search' => '',
                'filter' => 'all',
                'pagination' => $paginationResult,
                'currentPage' => 1,
                'totalPages' => 1,
            ])
            ->willReturn('<html>Email Log</html>');

        $response = $this->controller->index($request, $this->paginationService);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexWithSearchAndFilter(): void
    {
        $request = new Request(['search' => 'URGENT', 'filter' => 'sent']);
        
        $emails = [
            $this->createMockEmailSent('URGENT-001', 'sent'),
            $this->createMockEmailSent('URGENT-002', 'sent')
        ];
        
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);
        
        $this->emailSentRepository->method('createFilteredQueryBuilder')
            ->with('sent')
            ->willReturn($queryBuilder);

        $queryBuilder->method('andWhere')
            ->with('e.ticketId LIKE :search')
            ->willReturn($queryBuilder);
        
        $queryBuilder->method('setParameter')
            ->with('search', '%URGENT%')
            ->willReturn($queryBuilder);

        $queryBuilder->method('getQuery')
            ->willReturn($query);
        
        $query->method('getResult')
            ->willReturn($emails);

        $this->twig->method('render')
            ->with('email_log/index.html.twig', [
                'emails' => $emails,
                'search' => 'URGENT',
                'filter' => 'sent',
                'pagination' => null,
                'currentPage' => 1,
                'totalPages' => 1,
            ])
            ->willReturn('<html>Filtered Search Results</html>');

        $response = $this->controller->index($request, $this->paginationService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Filtered Search Results</html>', $response->getContent());
    }

    private function createMockEmailSent(string $ticketId, string $status): EmailSent
    {
        $emailSent = $this->createMock(EmailSent::class);
        $emailSent->method('getTicketId')->willReturn(TicketId::fromString($ticketId));
        $emailSent->method('getStatus')->willReturn(EmailStatus::fromString($status));
        return $emailSent;
    }
}