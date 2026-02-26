<?php

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Entity\User;
use App\Form\UserImportType;
use App\Dto\PaginationResult;
use App\Service\UserImportService;
use App\Dto\UserListingCriteria;
use App\Dto\UserListingResult;
use App\Service\UserListingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class UserControllerTest extends TestCase
{
    private UserController $controller;
    private UserListingService $listingService;
    private UserImportService $userImportService;
    private FormFactoryInterface $formFactory;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private UrlGeneratorInterface $urlGenerator;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->listingService = $this->createMock(UserListingService::class);
        $this->userImportService = $this->createMock(UserImportService::class);
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->controller = new UserController();

        $reflectionClass = new \ReflectionClass($this->controller);
        $containerProperty = $reflectionClass->getParentClass()->getProperty('container');
        $containerProperty->setAccessible(true);
        
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(fn ($service) => match ($service) {
                'form.factory' => $this->formFactory,
                'security.csrf.token_manager' => $this->csrfTokenManager,
                'router' => $this->urlGenerator,
                'twig' => $this->twig,
                default => null,
            });
        $container->method('has')->willReturn(true);
        
        $containerProperty->setValue($this->controller, $container);
    }

    public function testIndexWithoutSearchShowsPaginatedResults(): void
    {
        $request = new Request(['sort' => 'username', 'direction' => 'DESC', 'page' => '2']);
        
        $users = [new User(), new User()];
        $paginationResult = new PaginationResult(
            results: $users,
            currentPage: 2,
            totalPages: 5,
            totalItems: 20,
            itemsPerPage: 10,
            hasNext: true,
            hasPrevious: true
        );
        
        $listingResult = new UserListingResult(
            users: $users,
            pagination: $paginationResult,
            searchTerm: null,
            sortField: 'username',
            sortDirection: 'DESC'
        );

        $this->listingService->expects($this->once())
            ->method('listUsers')
            ->with($this->callback(static fn (UserListingCriteria $criteria) =>
                $criteria->sortField === 'username' &&
                $criteria->sortDirection === 'DESC' &&
                $criteria->page === 2
            ))
            ->willReturn($listingResult);

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

        $response = $this->controller->index($request, $this->listingService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>User Index</html>', $response->getContent());
    }

    public function testIndexWithSearchShowsAllSearchResults(): void
    {
        $request = new Request(['search' => 'john', 'sort' => 'email', 'direction' => 'ASC']);
        
        $users = [new User(), new User(), new User()];
        $listingResult = new UserListingResult(
            users: $users,
            pagination: null,
            searchTerm: 'john',
            sortField: 'email',
            sortDirection: 'ASC'
        );

        $this->listingService->expects($this->once())
            ->method('listUsers')
            ->with($this->callback(static fn (UserListingCriteria $criteria) =>
                $criteria->searchTerm === 'john' &&
                $criteria->sortField === 'email' &&
                $criteria->sortDirection === 'ASC'
            ))
            ->willReturn($listingResult);

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

        $response = $this->controller->index($request, $this->listingService);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Search Results</html>', $response->getContent());
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
        
        $form->expects($this->once())->method('handleRequest')->with($request);
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
