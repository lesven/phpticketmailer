<?php

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\UploadedFile;

class UserControllerTest extends TestCase
{
    public function testIndexCallsRepositoryAndRenders(): void
    {
        $user = $this->createMock(User::class);

        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->once())
            ->method('searchByUsername')
            ->with('john', 'username', 'DESC')
            ->willReturn([$user]);

        $controller = $this->getMockBuilder(UserController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with('user/index.html.twig', $this->callback(function ($params) use ($user) {
                return isset($params['users']) && $params['users'] === [$user]
                    && $params['searchTerm'] === 'john'
                    && $params['sortField'] === 'username'
                    && $params['sortDirection'] === 'DESC'
                    && $params['oppositeDirection'] === 'ASC';
            }))
            ->willReturn(new \Symfony\Component\HttpFoundation\Response('ok'));

        $request = Request::create('/user?search=john&sort=username&direction=DESC', 'GET');

        $response = $controller->index($request, $repo);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testExportReturnsCsvResponse(): void
    {
        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(1);
        $user1->method('getUsername')->willReturn('alice');
        $user1->method('getEmail')->willReturn('a@example.com');

        $user2 = $this->createMock(User::class);
        $user2->method('getId')->willReturn(2);
        $user2->method('getUsername')->willReturn('bob');
        $user2->method('getEmail')->willReturn('b@example.com');

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findAll')->willReturn([$user1, $user2]);

        $controller = new UserController();

        // Call export
        $response = $controller->export($repo);

        $this->assertEquals('text/csv', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertStringContainsString('ID,Username,Email', $content);
        $this->assertStringContainsString('1,"alice","a@example.com"', $content);
        $this->assertStringContainsString('2,"bob","b@example.com"', $content);
    }

    public function testImportValidCsvImportsUsers(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        $csv = "email,benutzername\nuser1@example.com,alice\nuser2@example.com,bob\n";
        file_put_contents($tmp, $csv);

        $uploaded = new class($tmp) {
            private $path;
            public function __construct($p) { $this->path = $p; }
            public function getPathname() { return $this->path; }
        };

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $csvField = $this->createMock(FormInterface::class);
        $csvField->method('getData')->willReturn($uploaded);

        $clearField = $this->createMock(FormInterface::class);
        $clearField->method('getData')->willReturn(false);

        $form->method('get')->willReturnMap([
            ['csvFile', $csvField],
            ['clearExisting', $clearField],
        ]);

        $controller = $this->getMockBuilder(UserController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->expects($this->once())->method('addFlash')->with('success', $this->stringContains('Benutzer erfolgreich importiert'));
        $controller->expects($this->once())->method('redirectToRoute')->with('user_index')->willReturn(new RedirectResponse('/user'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findAll')->willReturn([]); // Mock findAll to return empty array instead of null
        $entityManager->method('getRepository')->willReturn($userRepository);
        $entityManager->expects($this->exactly(2))->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $request = Request::create('/user/import', 'POST');

        $response = $controller->import($request, $entityManager);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testImportMissingColumnsRedirectsBack(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        $csv = "wrong,header\nval1,val2\n";
        file_put_contents($tmp, $csv);

        $uploaded = new class($tmp) {
            private $path;
            public function __construct($p) { $this->path = $p; }
            public function getPathname() { return $this->path; }
        };

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $csvField = $this->createMock(FormInterface::class);
        $csvField->method('getData')->willReturn($uploaded);

        $clearField = $this->createMock(FormInterface::class);
        $clearField->method('getData')->willReturn(false);

        $form->method('get')->willReturnMap([
            ['csvFile', $csvField],
            ['clearExisting', $clearField],
        ]);

        $controller = $this->getMockBuilder(UserController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createForm', 'redirectToRoute', 'addFlash', 'render'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->expects($this->once())->method('redirectToRoute')->with('user_import')->willReturn(new RedirectResponse('/user/import'));

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $request = Request::create('/user/import', 'POST');

        $response = $controller->import($request, $entityManager);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testNewCreatesUserWhenFormValid(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('createView')->willReturn(new FormView());

        $controller = $this->getMockBuilder(UserController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->expects($this->once())->method('addFlash')->with('success', $this->stringContains('Benutzer erfolgreich erstellt'));
        $controller->expects($this->once())->method('redirectToRoute')->with('user_index')->willReturn(new RedirectResponse('/user'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $request = Request::create('/user/new', 'POST');

        $response = $controller->new($request, $entityManager);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteWithCsrfValidRemovesUser(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(5);

        $controller = $this->getMockBuilder(UserController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->with('delete5', 'token')->willReturn(true);
        $controller->expects($this->once())->method('addFlash')->with('success', $this->stringContains('Benutzer erfolgreich gelöscht'));
        $controller->expects($this->once())->method('redirectToRoute')->with('user_index')->willReturn(new RedirectResponse('/user'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($user);
        $entityManager->expects($this->once())->method('flush');

        $request = Request::create('/user/5', 'POST', ['_token' => 'token']);

        $response = $controller->delete($request, $user, $entityManager);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testUnauthorizedAccessRedirectsToLogin(): void
    {
        $response = new RedirectResponse('/login');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/login', $response->headers->get('Location'));
    }

    public function testValidationErrorOnUserCreation(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());

        $controller = $this->getMockBuilder(UserController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createForm', 'render'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->expects($this->once())
            ->method('render')
            ->with('user/new.html.twig', $this->callback(function ($params) {
                return isset($params['form']) && $params['form'] instanceof FormView;
            }))
            ->willReturn(new \Symfony\Component\HttpFoundation\Response('ok'));

        $request = Request::create('/user/new', 'POST');

        $response = $controller->new($request, $this->createMock(EntityManagerInterface::class));

        $this->assertEquals(200, $response->getStatusCode());
    }
}
