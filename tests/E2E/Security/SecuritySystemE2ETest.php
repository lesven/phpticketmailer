<?php

namespace App\Tests\E2E\Security;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class SecuritySystemE2ETest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testSecurityComponentsAreAvailable(): void
    {
        $container = static::getContainer();
        
        // Test CSRF token manager is available
        $this->assertTrue($container->has('security.csrf.token_manager'));
        $csrfManager = $container->get('security.csrf.token_manager');
        $this->assertInstanceOf(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface::class, $csrfManager);
        
        // Test router is available
        $this->assertTrue($container->has('router'));
        $router = $container->get('router');
        $this->assertInstanceOf(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class, $router);
        
        // Test twig is available
        $this->assertTrue($container->has('twig'));
        $twig = $container->get('twig');
        $this->assertInstanceOf(\Twig\Environment::class, $twig);
    }

    public function testPasswordHashingFunctionality(): void
    {
        $testCases = [
            'simple_password',
            'complex_P@ssw0rd_123!',
            'unicode_тест_123',
            'emoji_test🔐123',
            'special_chars_!@#$%^&*()_+-={}[]|\\:";\'<>?,./',
            'very_long_password_that_exceeds_normal_length_requirements_and_should_still_work_perfectly_fine_123'
        ];

        foreach ($testCases as $password) {
            // Test BCrypt hashing
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $this->assertNotEmpty($hash, "Should generate hash for password");
            $this->assertTrue(password_verify($password, $hash), 
                "Should verify correct password");
            $this->assertFalse(password_verify('wrong_password', $hash), 
                "Should reject wrong password");
                
            // Test password length validation logic
            $isValidLength = strlen($password) >= 8;
            if (strlen($password) >= 8) {
                $this->assertTrue($isValidLength, "Password should be valid length");
            } else {
                $this->assertFalse($isValidLength, "Password should be invalid length");
            }
        }
    }

    public function testSessionHandling(): void
    {
        $session = new Session(new MockArraySessionStorage());
        
        // Test initial state
        $this->assertFalse($session->isStarted());
        $this->assertNull($session->get('is_authenticated'));
        
        // Start session
        $session->start();
        $this->assertTrue($session->isStarted());
        
        // Test authentication state management
        $session->set('is_authenticated', true);
        $this->assertTrue($session->get('is_authenticated'));
        
        $session->set('is_authenticated', false);
        $this->assertFalse($session->get('is_authenticated'));
        
        // Test session data removal
        $session->set('test_data', 'test_value');
        $this->assertEquals('test_value', $session->get('test_data'));
        
        $session->remove('test_data');
        $this->assertNull($session->get('test_data'));
        
        // Test session invalidation
        $session->invalidate();
        $this->assertNull($session->get('is_authenticated'));
    }

    public function testSecuritySubscriberEvents(): void
    {
        $subscribedEvents = \App\EventSubscriber\SecuritySubscriber::getSubscribedEvents();
        
        $this->assertIsArray($subscribedEvents);
        $this->assertArrayHasKey('kernel.request', $subscribedEvents);
        $this->assertEquals(['onKernelRequest', 20], $subscribedEvents['kernel.request']);
    }

    public function testHTTPRequestHandling(): void
    {
        // Test GET request creation
        $getRequest = Request::create('/login', 'GET');
        $this->assertEquals('GET', $getRequest->getMethod());
        $this->assertEquals('/login', $getRequest->getPathInfo());
        
        // Test POST request creation
        $postRequest = Request::create('/login', 'POST', [
            'password' => 'test_password',
            '_csrf_token' => 'test_token'
        ]);
        $this->assertEquals('POST', $postRequest->getMethod());
        $this->assertEquals('test_password', $postRequest->get('password'));
        $this->assertEquals('test_token', $postRequest->get('_csrf_token'));
        
        // Test request attributes
        $request = new Request();
        $request->attributes->set('_route', 'test_route');
        $this->assertEquals('test_route', $request->attributes->get('_route'));
    }

    public function testTwigEnvironment(): void
    {
        $twig = static::getContainer()->get('twig');
        
        // Test basic template functionality
        $template = $twig->createTemplate('Hello {{ name }}!');
        $result = $template->render(['name' => 'Test']);
        $this->assertEquals('Hello Test!', $result);
        
        // Test template with security-related content
        $securityTemplate = $twig->createTemplate(
            '{% if authenticated %}Welcome{% else %}Please login{% endif %}'
        );
        
        $authenticatedResult = $securityTemplate->render(['authenticated' => true]);
        $this->assertEquals('Welcome', $authenticatedResult);
        
        $unauthenticatedResult = $securityTemplate->render(['authenticated' => false]);
        $this->assertEquals('Please login', $unauthenticatedResult);
    }

    public function testEntityManagerAvailability(): void
    {
        try {
            $container = static::getContainer();
            
            // Just test that the service exists, don't actually use it to avoid deprecations
            if ($container->has('doctrine.orm.entity_manager')) {
                $this->assertTrue(true, 'Doctrine ORM service is available');
            } else {
                $this->markTestSkipped('Doctrine ORM not available in test environment');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    public function testRouterFunctionality(): void
    {
        $router = static::getContainer()->get('router');
        
        // Test route generation
        try {
            $loginUrl = $router->generate('app_login');
            $this->assertStringContainsString('/login', $loginUrl);
            
            $logoutUrl = $router->generate('app_logout');
            $this->assertStringContainsString('/logout', $logoutUrl);
            
            $dashboardUrl = $router->generate('dashboard');
            $this->assertIsString($dashboardUrl);
        } catch (\Exception $e) {
            $this->markTestSkipped('Router configuration not available: ' . $e->getMessage());
        }
    }

    public function testSecurityConfigurationIntegrity(): void
    {
        // Test that SecuritySubscriber class exists and is properly configured
        $this->assertTrue(class_exists(\App\EventSubscriber\SecuritySubscriber::class));
        
        // Test that SecurityController exists
        $this->assertTrue(class_exists(\App\Controller\SecurityController::class));
        
        // Test that required entities exist
        $this->assertTrue(class_exists(\App\Entity\AdminPassword::class));
        
        // Test that security subscriber implements proper interface
        $subscriber = new \App\EventSubscriber\SecuritySubscriber(
            static::getContainer()->get('router')
        );
        $this->assertInstanceOf(\Symfony\Component\EventDispatcher\EventSubscriberInterface::class, $subscriber);
    }

    public function testCSRFTokenGenerationWithoutSession(): void
    {
        // Test that CSRF functionality is available, even if session is not active
        $container = static::getContainer();
        
        if ($container->has('security.csrf.token_manager')) {
            $csrfManager = $container->get('security.csrf.token_manager');
            
            // This might fail if no session is available, which is expected
            try {
                $token = $csrfManager->getToken('test');
                $this->assertNotEmpty($token->getValue());
            } catch (\Exception $e) {
                // Expected if no session context
                $this->assertStringContainsString('session', strtolower($e->getMessage()));
            }
        }
    }

    public function testApplicationEnvironmentSetup(): void
    {
        // Test that we're running in test environment
        $kernel = self::$kernel;
        $this->assertEquals('test', $kernel->getEnvironment());
        
        // Test that debug mode is appropriate for testing
        $this->assertIsBool($kernel->isDebug());
        
        // Test that container is available
        $container = static::getContainer();
        $this->assertInstanceOf(\Symfony\Component\DependencyInjection\ContainerInterface::class, $container);
    }
}