<?php

namespace App\Tests\Controller;

use App\Controller\SecurityController;
use App\Exception\WeakPasswordException;
use App\Service\AuthenticationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class SecurityControllerTest extends TestCase
{
    private SecurityController $controller;
    private AuthenticationService $authenticationService;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private UrlGeneratorInterface $urlGenerator;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->authenticationService = $this->createMock(AuthenticationService::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->controller = new SecurityController($this->authenticationService, $this->csrfTokenManager);

        // Inject mocked services using reflection
        $reflectionClass = new \ReflectionClass($this->controller);
        $containerProperty = $reflectionClass->getParentClass()->getProperty('container');
        $containerProperty->setAccessible(true);
        
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function($service) {
                return match($service) {
                    'router' => $this->urlGenerator,
                    'twig' => $this->twig,
                    default => null
                };
            });
        $container->method('has')->willReturn(true);
        
        $containerProperty->setValue($this->controller, $container);
    }

    public function testLoginRedirectsToIsDashboardWhenAlreadyAuthenticated(): void
    {
        $request = new Request();
        $session = $this->createMock(SessionInterface::class);
        
        $session->method('get')->with('is_authenticated')->willReturn(true);
        
        $this->urlGenerator->method('generate')
            ->with('dashboard')
            ->willReturn('/dashboard');

        $response = $this->controller->login($request, $session);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/dashboard', $response->headers->get('Location'));
    }

    public function testLoginShowsFormWhenNotAuthenticated(): void
    {
        $request = new Request();
        $session = $this->createMock(SessionInterface::class);
        
        $session->method('get')->with('is_authenticated')->willReturn(false);

        $this->twig->method('render')
            ->with('security/login.html.twig', [
                'error' => null,
            ])
            ->willReturn('<html>Login Form</html>');

        $response = $this->controller->login($request, $session);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Login Form</html>', $response->getContent());
    }

    public function testLoginWithValidCredentialsAuthenticatesUser(): void
    {
        $request = new Request([], [
            'password' => 'correct_password',
            '_csrf_token' => 'valid_token'
        ]);
        $request->setMethod('POST');
        
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(false);
        
        $this->csrfTokenManager->method('isTokenValid')
            ->with($this->callback(function($token) {
                return $token instanceof CsrfToken && $token->getId() === 'authenticate';
            }))
            ->willReturn(true);

        $this->authenticationService->method('authenticate')
            ->with('correct_password')
            ->willReturn(true);

        $session->expects($this->once())
            ->method('set')
            ->with('is_authenticated', true);

        $this->urlGenerator->method('generate')
            ->with('dashboard')
            ->willReturn('/dashboard');

        $response = $this->controller->login($request, $session);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/dashboard', $response->headers->get('Location'));
    }

    public function testLoginWithInvalidPasswordShowsError(): void
    {
        $request = new Request([], [
            'password' => 'wrong_password',
            '_csrf_token' => 'valid_token'
        ]);
        $request->setMethod('POST');
        
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(false);
        
        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(true);

        $this->authenticationService->method('authenticate')
            ->with('wrong_password')
            ->willReturn(false);

        $this->twig->method('render')
            ->with('security/login.html.twig', [
                'error' => 'Ungültiges Passwort',
            ])
            ->willReturn('<html>Login Form with Error</html>');

        $response = $this->controller->login($request, $session);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Login Form with Error</html>', $response->getContent());
    }

    public function testLoginWithInvalidCsrfTokenShowsError(): void
    {
        $request = new Request([], [
            'password' => 'any_password',
            '_csrf_token' => 'invalid_token'
        ]);
        $request->setMethod('POST');
        
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(false);
        
        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(false);

        $this->twig->method('render')
            ->with('security/login.html.twig', [
                'error' => 'Ein Fehler ist aufgetreten: Invalid CSRF token',
            ])
            ->willReturn('<html>Login Form with CSRF Error</html>');

        $response = $this->controller->login($request, $session);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Login Form with CSRF Error</html>', $response->getContent());
    }

    public function testLogoutRemovesAuthenticationAndRedirects(): void
    {
        $session = $this->createMock(SessionInterface::class);
        
        $session->expects($this->once())
            ->method('remove')
            ->with('is_authenticated');

        $this->urlGenerator->method('generate')
            ->with('app_login')
            ->willReturn('/login');

        $response = $this->controller->logout($session);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function testChangePasswordShowsFormWhenNotSubmitted(): void
    {
        $request = new Request();

        $this->twig->method('render')
            ->with('security/change_password.html.twig', [
                'error' => null,
                'success' => null,
            ])
            ->willReturn('<html>Change Password Form</html>');

        $response = $this->controller->changePassword($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Change Password Form</html>', $response->getContent());
    }

    public function testChangePasswordWithValidDataChangesPassword(): void
    {
        $request = new Request([], [
            'current_password' => 'old_password',
            'new_password' => 'new_password_123',
            '_csrf_token' => 'valid_token'
        ]);
        $request->setMethod('POST');
        
        $this->csrfTokenManager->method('isTokenValid')
            ->with($this->callback(function($token) {
                return $token instanceof CsrfToken && $token->getId() === 'change_password';
            }))
            ->willReturn(true);

        $this->authenticationService->method('changePassword')
            ->with('old_password', 'new_password_123')
            ->willReturn(true);

        $this->twig->method('render')
            ->with('security/change_password.html.twig', [
                'error' => null,
                'success' => 'Passwort erfolgreich geändert.',
            ])
            ->willReturn('<html>Password Changed</html>');

        $response = $this->controller->changePassword($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Password Changed</html>', $response->getContent());
    }

    public function testChangePasswordWithShortNewPasswordShowsError(): void
    {
        $request = new Request([], [
            'current_password' => 'old_password',
            'new_password' => 'short',
            '_csrf_token' => 'valid_token'
        ]);
        $request->setMethod('POST');
        
        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(true);

        $this->authenticationService->method('changePassword')
            ->willThrowException(new WeakPasswordException('Passwort muss mindestens 8 Zeichen lang sein'));

        $this->twig->method('render')
            ->with('security/change_password.html.twig', $this->callback(function($context) {
                return isset($context['error']) && str_contains($context['error'], 'Schwaches Passwort') &&
                       $context['success'] === null;
            }))
            ->willReturn('<html>Password Too Weak Error</html>');

        $response = $this->controller->changePassword($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Password Too Weak Error</html>', $response->getContent());
    }

    public function testChangePasswordWithWrongCurrentPasswordShowsError(): void
    {
        $request = new Request([], [
            'current_password' => 'wrong_current_password',
            'new_password' => 'new_password_123',
            '_csrf_token' => 'valid_token'
        ]);
        $request->setMethod('POST');
        
        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(true);

        $this->authenticationService->method('changePassword')
            ->with('wrong_current_password', 'new_password_123')
            ->willReturn(false);

        $this->twig->method('render')
            ->with('security/change_password.html.twig', [
                'error' => 'Das aktuelle Passwort ist nicht korrekt.',
                'success' => null,
            ])
            ->willReturn('<html>Wrong Current Password Error</html>');

        $response = $this->controller->changePassword($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Wrong Current Password Error</html>', $response->getContent());
    }

    public function testChangePasswordWithInvalidCsrfTokenShowsError(): void
    {
        $request = new Request([], [
            'current_password' => 'old_password',
            'new_password' => 'new_password_123',
            '_csrf_token' => 'invalid_token'
        ]);
        $request->setMethod('POST');
        
        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(false);

        $this->twig->method('render')
            ->with('security/change_password.html.twig', [
                'error' => 'Ein Fehler ist aufgetreten: Invalid CSRF token',
                'success' => null,
            ])
            ->willReturn('<html>CSRF Error</html>');

        $response = $this->controller->changePassword($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>CSRF Error</html>', $response->getContent());
    }

    public function testLoginHandlesAuthenticationServiceException(): void
    {
        $request = new Request([], [
            'password' => 'any_password',
            '_csrf_token' => 'valid_token'
        ]);
        $request->setMethod('POST');
        
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(false);
        
        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(true);

        $this->authenticationService->method('authenticate')
            ->willThrowException(new \Exception('Database error'));

        $this->twig->method('render')
            ->with('security/login.html.twig', [
                'error' => 'Ein Fehler ist aufgetreten: Database error',
            ])
            ->willReturn('<html>Database Error</html>');

        $response = $this->controller->login($request, $session);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Database Error</html>', $response->getContent());
    }

    public function testChangePasswordHandlesServiceException(): void
    {
        $request = new Request([], [
            'current_password' => 'old_password',
            'new_password' => 'new_password_123',
            '_csrf_token' => 'valid_token'
        ]);
        $request->setMethod('POST');
        
        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(true);

        $this->authenticationService->method('changePassword')
            ->willThrowException(new \Exception('Database error'));

        $this->twig->method('render')
            ->with('security/change_password.html.twig', [
                'error' => 'Ein Fehler ist aufgetreten: Database error',
                'success' => null,
            ])
            ->willReturn('<html>Database Error</html>');

        $response = $this->controller->changePassword($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<html>Database Error</html>', $response->getContent());
    }
}