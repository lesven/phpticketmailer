<?php

namespace App\Tests\E2E\Security;

use App\EventSubscriber\SecuritySubscriber;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SecuritySubscriberE2ETest extends KernelTestCase
{
    private SecuritySubscriber $securitySubscriber;
    private UrlGeneratorInterface $urlGenerator;

    protected function setUp(): void
    {
        self::bootKernel();
        
        try {
            $this->urlGenerator = static::getContainer()->get('router');
            $this->securitySubscriber = new SecuritySubscriber($this->urlGenerator);
        } catch (\Exception $e) {
            $this->markTestSkipped('Kernel services not available for E2E tests: ' . $e->getMessage());
        }
    }

    public function testProtectedRoutesRedirectToLogin(): void
    {
        $protectedRoutes = [
            ['path' => '/', 'route' => 'dashboard'],
            ['path' => '/users', 'route' => 'user_index'],
            ['path' => '/users/new', 'route' => 'user_new'],
            ['path' => '/csv/upload', 'route' => 'csv_upload'],
            ['path' => '/email-log', 'route' => 'email_log_index'],
            ['path' => '/smtp/edit', 'route' => 'smtp_config_edit'],
            ['path' => '/password', 'route' => 'change_password']
        ];

        foreach ($protectedRoutes as $routeData) {
            // Create request without authentication
            $request = Request::create($routeData['path'], 'GET');
            $request->attributes->set('_route', $routeData['route']);
            $session = new Session(new MockArraySessionStorage());
            $request->setSession($session);

            // Create request event
            $event = new RequestEvent(
                self::$kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST
            );

            // Process with security subscriber
            $this->securitySubscriber->onKernelRequest($event);

            // Should redirect to login
            $response = $event->getResponse();
            $this->assertNotNull($response, "Route {$routeData['path']} should have response set");
            $this->assertTrue($response->isRedirect(), "Route {$routeData['path']} should redirect");
            $this->assertStringContainsString('/login', $response->getTargetUrl(), 
                "Route {$routeData['path']} should redirect to login");
        }
    }

    public function testPublicRoutesAreAllowed(): void
    {
        $publicRoutes = [
            ['path' => '/login', 'route' => 'app_login'],
            ['path' => '/monitoring/health', 'route' => 'app_monitoring_health'],
            ['path' => '/monitoring/database', 'route' => 'app_monitoring_database'],
            ['path' => '/bundles/framework/css/structure.css', 'route' => null],
            ['path' => '/_profiler', 'route' => '_profiler'],
            ['path' => '/_wdt/123', 'route' => '_wdt']
        ];

        foreach ($publicRoutes as $routeData) {
            // Create request
            $request = Request::create($routeData['path'], 'GET');
            if ($routeData['route']) {
                $request->attributes->set('_route', $routeData['route']);
            }
            $session = new Session(new MockArraySessionStorage());
            $request->setSession($session);

            // Create request event
            $event = new RequestEvent(
                self::$kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST
            );

            // Process with security subscriber
            $this->securitySubscriber->onKernelRequest($event);

            // Should not redirect
            $response = $event->getResponse();
            $this->assertNull($response, "Public route {$routeData['path']} should not have response set");
        }
    }

    public function testAuthenticatedUserCanAccessProtectedRoutes(): void
    {
        $protectedRoutes = [
            ['path' => '/', 'route' => 'dashboard'],
            ['path' => '/users', 'route' => 'user_index'],
            ['path' => '/csv/upload', 'route' => 'csv_upload']
        ];

        foreach ($protectedRoutes as $routeData) {
            // Create request with authentication
            $request = Request::create($routeData['path'], 'GET');
            $request->attributes->set('_route', $routeData['route']);
            $session = new Session(new MockArraySessionStorage());
            $session->set('is_authenticated', true); // Authenticated session
            $request->setSession($session);

            // Create request event
            $event = new RequestEvent(
                self::$kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST
            );

            // Process with security subscriber
            $this->securitySubscriber->onKernelRequest($event);

            // Should not redirect
            $response = $event->getResponse();
            $this->assertNull($response, "Authenticated user should access {$routeData['path']}");
        }
    }

    public function testSubRequestsAreIgnored(): void
    {
        // Create sub-request (not main request)
        $request = Request::create('/protected-route', 'GET');
        $request->attributes->set('_route', 'some_protected_route');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        // Create sub-request event
        $event = new RequestEvent(
            self::$kernel,
            $request,
            HttpKernelInterface::SUB_REQUEST // Sub request
        );

        // Process with security subscriber
        $this->securitySubscriber->onKernelRequest($event);

        // Should not redirect sub-requests
        $response = $event->getResponse();
        $this->assertNull($response, 'Sub-requests should not be processed by security subscriber');
    }

    public function testEventSubscriberConfiguration(): void
    {
        $subscribedEvents = SecuritySubscriber::getSubscribedEvents();
        
        $this->assertArrayHasKey('kernel.request', $subscribedEvents);
        $this->assertEquals(['onKernelRequest', 20], $subscribedEvents['kernel.request']);
    }

    public function testMonitoringEndpointsWithDifferentPaths(): void
    {
        // Test both route-based and path-based detection of monitoring endpoints
        $monitoringPaths = [
            '/monitoring/health',
            '/monitoring/database'
        ];

        foreach ($monitoringPaths as $path) {
            // Test with route
            $request = Request::create($path, 'GET');
            $request->attributes->set('_route', 'app_monitoring_health');
            $session = new Session(new MockArraySessionStorage());
            $request->setSession($session);

            $event = new RequestEvent(
                self::$kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST
            );

            $this->securitySubscriber->onKernelRequest($event);
            $this->assertNull($event->getResponse(), "Monitoring route {$path} should be public");

            // Test with path only (no route)
            $request = Request::create($path, 'GET');
            $session = new Session(new MockArraySessionStorage());
            $request->setSession($session);

            $event = new RequestEvent(
                self::$kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST
            );

            $this->securitySubscriber->onKernelRequest($event);
            $this->assertNull($event->getResponse(), "Monitoring path {$path} should be public");
        }
    }
}