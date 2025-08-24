<?php

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Entity\User;
use App\Form\UserType;
use App\Form\UserImportType;
use App\Repository\UserRepository;
use App\Service\PaginationService;
use App\Service\UserImportService;
use App\Service\PaginationResult;
use App\Service\UserImportResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class UserControllerTest extends TestCase
{
    private UserController $controller;
    private UserRepository $userRepository;
    private PaginationService $paginationService;
    private UserImportService $userImportService;
    private EntityManagerInterface $entityManager;
    private FormFactoryInterface $formFactory;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private UrlGeneratorInterface $urlGenerator;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->paginationService = $this->createMock(PaginationService::class);
        $this->userImportService = $this->createMock(UserImportService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->controller = new UserController();

        // Inject mocked services using reflection
        $reflectionClass = new \ReflectionClass($this->controller);
        $containerProperty = $reflectionClass->getParentClass()->getProperty('container');
        $containerProperty->setAccessible(true);
        
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function($service) {
                return match($service) {
                    'form.factory' => $this->formFactory,
                    'security.csrf.token_manager' => $this->csrfTokenManager,
                    'router' => $this->urlGenerator,
                    'twig' => $this->twig,
                    default => null
                };
            });
        $container->method('has')->willReturn(true);
        
        $containerProperty->setValue($this->controller, $container);
    }

    public function testIndexWithoutSearchShowsPaginatedResults(): void
    {
        $request = new Request(['sort' => 'username', 'direction' => 'DESC', 'page' => '2']);
        
        $users = [new User(), new User()];
        $queryBuilder = $this->createMock(QueryBuilder::class);
        
        $this->userRepository->method('createSortedQueryBuilder')
            ->with('username', 'DESC')
            ->willReturn($queryBuilder);

        $paginationResult = new PaginationResult(
            results: $users, 
            currentPage: 2, 
            totalPages: 5,
            totalItems: 20,
            itemsPerPage: 10,
            hasNext: true,
            hasPrevious: true
        );
        
        $this->paginationService->method('paginate')
            ->with($queryBuilder, 2)
            ->willReturn($paginationResult);

        $this->twig->method('render')
            ->with('user/index.html.twig', [
                'users' => $users,
                'searchTerm' => null,
                'sortField' => 'username',
                'sortDirection' => 'DESC',
                'oppositeDirection' => 'ASC',
                'pagination' => $paginationResult,
                'hasSearch' => false,
                'currentPage' => 2,
                'totalPages' => 5,
                'totalUsers' => 20
            ])
            ->willReturn('<html>User Index</html>');

        $response = $this->controller->index($request, $this->userRepository, $this->paginationService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>User Index</html>', $response->getContent());
    }

    public function testIndexWithSearchShowsAllSearchResults(): void
    {
        $request = new Request(['search' => 'john', 'sort' => 'email', 'direction' => 'ASC']);
        
        $users = [new User(), new User(), new User()];
        
        $this->userRepository->method('searchByUsername')
            ->with('john', 'email', 'ASC')
            ->willReturn($users);

        $this->twig->method('render')
            ->with('user/index.html.twig', [
                'users' => $users,
                'searchTerm' => 'john',
                'sortField' => 'email',
                'sortDirection' => 'ASC',
                'oppositeDirection' => 'DESC',
                'pagination' => null,
                'hasSearch' => true,
                'currentPage' => 1,
                'totalPages' => 1,
                'totalUsers' => 3
            ])
            ->willReturn('<html>Search Results</html>');

        $response = $this->controller->index($request, $this->userRepository, $this->paginationService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Search Results</html>', $response->getContent());
    }

    public function testIndexWithInvalidPageDefaultsToPageOne(): void
    {
        $request = new Request(['page' => '-5']);
        
        $users = [new User()];
        $queryBuilder = $this->createMock(QueryBuilder::class);
        
        $this->userRepository->method('createSortedQueryBuilder')
            ->with('id', 'ASC')
            ->willReturn($queryBuilder);

        $paginationResult = new PaginationResult(
            results: $users, 
            currentPage: 1,
            totalPages: 1,
            totalItems: 1,
            itemsPerPage: 10,
            hasNext: false,
            hasPrevious: false
        );
        
        $this->paginationService->method('paginate')
            ->with($queryBuilder, 1) // Should be 1, not -5
            ->willReturn($paginationResult);

        $this->twig->method('render')->willReturn('<html>Users</html>');

        $response = $this->controller->index($request, $this->userRepository, $this->paginationService);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testExportReturnsCSVFile(): void
    {
        $csvContent = "username,email,excludedFromSurveys\nuser1,user1@example.com,0\nuser2,user2@example.com,1";
        
        $this->userImportService->method('exportUsersToCsv')
            ->willReturn($csvContent);

        $response = $this->controller->export($this->userImportService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($csvContent, $response->getContent());
        $this->assertEquals('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('users_export_', $response->headers->get('Content-Disposition'));
    }

    public function testImportShowsFormWhenNotSubmitted(): void
    {
        $request = new Request();
        
        $form = $this->createMock(FormInterface::class);
        $formView = $this->createMock(FormView::class);
        
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn($formView);

        $this->formFactory->method('create')
            ->with(UserImportType::class)
            ->willReturn($form);

        $this->twig->method('render')
            ->with('user/import.html.twig', [
                'form' => $formView,
            ])
            ->willReturn('<html>Import Form</html>');

        $response = $this->controller->import($request, $this->userImportService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Import Form</html>', $response->getContent());
    }

}