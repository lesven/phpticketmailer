<?php

use PHPUnit\Framework\TestCase;
use App\EventSubscriber\SecuritySubscriber;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class SecuritySubscriberTest extends TestCase
{
    public function testNonMainRequestDoesNothing(): void
    {
        $urlGen = $this->createMock(UrlGeneratorInterface::class);

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->once())->method('isMainRequest')->willReturn(false);
        $event->expects($this->never())->method('getRequest');
        $event->expects($this->never())->method('setResponse');

        $subscriber = new SecuritySubscriber($urlGen);
        $subscriber->onKernelRequest($event);
    }

    public function testWhitelistedRouteDoesNothing(): void
    {
        $urlGen = $this->createMock(UrlGeneratorInterface::class);

        $request = Request::create('/login');
        $session = $this->createMock(SessionInterface::class);
        $request->setSession($session);
        $request->attributes->set('_route', 'app_login');

        $event = $this->createMock(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);
        $event->method('getRequest')->willReturn($request);
        $event->expects($this->never())->method('setResponse');

        $urlGen->expects($this->never())->method('generate');

        $subscriber = new SecuritySubscriber($urlGen);
        $subscriber->onKernelRequest($event);
    }

    public function testPathPrefixWhitelistedDoesNothing(): void
    {
        $urlGen = $this->createMock(UrlGeneratorInterface::class);

        $request = Request::create('/bundles/some/asset.js');
        $session = $this->createMock(SessionInterface::class);
        $request->setSession($session);
        $request->attributes->set('_route', null);

        $event = $this->createMock(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);
        $event->method('getRequest')->willReturn($request);
        $event->expects($this->never())->method('setResponse');

        $urlGen->expects($this->never())->method('generate');

        $subscriber = new SecuritySubscriber($urlGen);
        $subscriber->onKernelRequest($event);
    }

    public function testUnauthenticatedUserGetsRedirect(): void
    {
        $urlGen = $this->createMock(UrlGeneratorInterface::class);

        $request = Request::create('/secure');
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(false);
        $request->setSession($session);
        $request->attributes->set('_route', null);

        $event = $this->createMock(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);
        $event->method('getRequest')->willReturn($request);

        $urlGen->expects($this->once())->method('generate')->with('app_login')->willReturn('/login');

        $event->expects($this->once())->method('setResponse')->with($this->callback(function ($resp) {
            return $resp instanceof RedirectResponse && $resp->getTargetUrl() === '/login';
        }));

        $subscriber = new SecuritySubscriber($urlGen);
        $subscriber->onKernelRequest($event);
    }

    public function testAuthenticatedUserNoRedirect(): void
    {
        $urlGen = $this->createMock(UrlGeneratorInterface::class);

        $request = Request::create('/secure');
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('is_authenticated')->willReturn(true);
        $request->setSession($session);
        $request->attributes->set('_route', null);

        $event = $this->createMock(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);
        $event->method('getRequest')->willReturn($request);

        $urlGen->expects($this->never())->method('generate');
        $event->expects($this->never())->method('setResponse');

        $subscriber = new SecuritySubscriber($urlGen);
        $subscriber->onKernelRequest($event);
    }
}
