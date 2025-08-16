<?php

namespace App\Tests\Controller;

use App\Controller\SecurityController;
use App\Entity\AdminPassword;
use Doctrine\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Unit tests for SecurityController
 */
class SecurityControllerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private SecurityController $controller;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);

        $this->controller = new SecurityController($this->entityManager, $this->csrfTokenManager);
    }

    private function setContainerMocks($twig = null, $router = null): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function ($id) {
            return in_array($id, ['twig', 'router'], true);
        });
        $container->method('get')->willReturnCallback(function ($id) use ($twig, $router) {
            if ($id === 'twig') {
                return $twig;
            }
            if ($id === 'router') {
                return $router;
            }
            return null;
        });

        $reflection = new \ReflectionClass($this->controller);
        $setContainerMethod = $reflection->getMethod('setContainer');
        $setContainerMethod->setAccessible(true);
        $setContainerMethod->invoke($this->controller, $container);

        return $container;
    }

    public function testLoginRedirectsWhenAlreadyAuthenticated(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(true);

    $router = $this->createMock(RouterInterface::class);
    $router->method('generate')->willReturn('/dashboard');

        $this->setContainerMocks(null, $router);

        $request = Request::create('/login', 'GET');

        $response = $this->controller->login($request, $session);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testLoginPostValidPasswordAuthenticatesAndRedirects(): void
    {
        $passwordPlain = 'mypassword';
        $hashed = password_hash($passwordPlain, PASSWORD_BCRYPT);

        $adminPassword = $this->createMock(AdminPassword::class);
        $adminPassword->method('getPassword')->willReturn($hashed);

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($adminPassword);

        $this->entityManager->method('getRepository')->willReturn($repo);

        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('set')->with('is_authenticated', true);

    $router = $this->createMock(RouterInterface::class);
    $router->method('generate')->willReturn('/dashboard');

        $this->setContainerMocks(null, $router);

        $request = Request::create('/login', 'POST', ['_csrf_token' => 'token', 'password' => $passwordPlain]);

        $response = $this->controller->login($request, $session);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testLoginPostInvalidPasswordRendersError(): void
    {
        $passwordPlain = 'wrong';
        $hashed = password_hash('correct', PASSWORD_BCRYPT);

        $adminPassword = $this->createMock(AdminPassword::class);
        $adminPassword->method('getPassword')->willReturn($hashed);

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($adminPassword);

        $this->entityManager->method('getRepository')->willReturn($repo);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->never())->method('set');

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('security/login.html.twig', $this->callback(function ($params) {
                return isset($params['error']) && $params['error'] === 'Ungültiges Passwort';
            }))
            ->willReturn('<html>Login with error</html>');

    $router = $this->createMock(RouterInterface::class);
    $router->method('generate')->willReturn('/dashboard');

        $this->setContainerMocks($twig, $router);

        $request = Request::create('/login', 'POST', ['_csrf_token' => 'token', 'password' => $passwordPlain]);

        $response = $this->controller->login($request, $session);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Login with error', $response->getContent());
    }

    public function testLogoutRemovesSessionAndRedirects(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('remove')->with('is_authenticated');

    $router = $this->createMock(RouterInterface::class);
    $router->method('generate')->willReturn('/login');

        $this->setContainerMocks(null, $router);

        $response = $this->controller->logout($session);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testChangePasswordSuccess(): void
    {
        $current = 'oldpassword';
        $new = 'newsecurepassword';

        $hashedCurrent = password_hash($current, PASSWORD_BCRYPT);

        $adminPassword = $this->createMock(AdminPassword::class);
        $adminPassword->method('getPassword')->willReturn($hashedCurrent);
        $adminPassword->expects($this->once())->method('setPassword');

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($adminPassword);

        $this->entityManager->method('getRepository')->willReturn($repo);
        $this->entityManager->expects($this->once())->method('flush');

        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('security/change_password.html.twig', $this->callback(function ($params) {
                return isset($params['success']) && $params['success'] === 'Passwort erfolgreich geändert.';
            }))
            ->willReturn('<html>Password changed</html>');

    $router = $this->createMock(RouterInterface::class);
    $router->method('generate')->willReturn('/');

        $this->setContainerMocks($twig, $router);

        $request = Request::create('/password', 'POST', ['_csrf_token' => 'token', 'current_password' => $current, 'new_password' => $new]);

        $response = $this->controller->changePassword($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Password changed', $response->getContent());
    }

    public function testChangePasswordShortNewPasswordShowsError(): void
    {
        $current = 'oldpassword';
        $new = 'short';

        $hashedCurrent = password_hash($current, PASSWORD_BCRYPT);

        $adminPassword = $this->createMock(AdminPassword::class);
        $adminPassword->method('getPassword')->willReturn($hashedCurrent);

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($adminPassword);

        $this->entityManager->method('getRepository')->willReturn($repo);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('security/change_password.html.twig', $this->callback(function ($params) {
                return isset($params['error']) && $params['error'] === 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
            }))
            ->willReturn('<html>Password too short</html>');

    $router = $this->createMock(RouterInterface::class);
    $router->method('generate')->willReturn('/');

        $this->setContainerMocks($twig, $router);

        $request = Request::create('/password', 'POST', ['_csrf_token' => 'token', 'current_password' => $current, 'new_password' => $new]);

        $response = $this->controller->changePassword($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Password too short', $response->getContent());
    }
}
