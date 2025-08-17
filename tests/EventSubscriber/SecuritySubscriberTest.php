<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\SecuritySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SecuritySubscriberTest extends TestCase
{
    public function testPublicRoutesAreAllowed(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $subscriber = new SecuritySubscriber($urlGenerator);

        $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/bundles/some/file.js', 'GET');

    // SecuritySubscriber calls $request->getSession(); provide a mock to avoid exception
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturn(null);
    $request->setSession($session);

    $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

    // Should not set a response (no redirect)
    $subscriber->onKernelRequest($event);

        $request = Request::create('/bundles/some/file.js', 'GET');

        // SecuritySubscriber calls $request->getSession(); provide a mock to avoid exception
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn(null);
        $request->setSession($session);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not set a response (no redirect)
        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testRedirectsToLoginWhenNotAuthenticated(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->with('app_login')->willReturn('/login');
        $subscriber = new SecuritySubscriber($urlGenerator);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/dashboard', 'GET');

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(false);

        $request->setSession($session);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/login', $response->getTargetUrl());
    }

    public function testAllowsWhenAuthenticated(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $subscriber = new SecuritySubscriber($urlGenerator);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/dashboard', 'GET');

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(true);

        $request->setSession($session);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testProfilerPathsAreAllowed(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $subscriber = new SecuritySubscriber($urlGenerator);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/_profiler/abcd', 'GET');

        // unauthenticated session
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(false);
        $request->setSession($session);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        // profiler paths should be allowed without redirect
        $this->assertNull($event->getResponse());
    }

    public function testWdtPathIsAllowed(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $subscriber = new SecuritySubscriber($urlGenerator);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/_wdt/12345', 'GET');

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(false);
        $request->setSession($session);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testLoginRouteIsAllowed(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $subscriber = new SecuritySubscriber($urlGenerator);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/login', 'GET');
        // set route attribute to simulate named route
        $request->attributes->set('_route', 'app_login');

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(false);
        $request->setSession($session);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testIgnoresSubrequests(): void
    {
        // Ensure that SUB_REQUEST is ignored and urlGenerator is not called
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $subscriber = new SecuritySubscriber($urlGenerator);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/dashboard', 'GET');

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(false);
        $request->setSession($session);

        // create a sub-request (not main request)
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }
}
