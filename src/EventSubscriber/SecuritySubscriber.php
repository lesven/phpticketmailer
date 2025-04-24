<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SecuritySubscriber implements EventSubscriberInterface
{
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        $route = $request->attributes->get('_route');
        $pathInfo = $request->getPathInfo();
        
        // Öffentliche Routen und Assets ohne Authentifizierung erlauben
        if ($route === 'app_login' || 
            $route === '_wdt' || 
            $route === '_profiler' || 
            $route === '_profiler_home' ||
            $route === '_profiler_search' ||
            $route === '_profiler_search_bar' ||
            $route === '_profiler_phpinfo' ||
            $route === '_profiler_search_results' ||
            $route === '_profiler_open_file' ||
            $route === '_profiler_router' ||
            $route === '_profiler_exception' ||
            $route === '_profiler_exception_css' ||
            strpos($pathInfo, '/bundles/') === 0 ||
            strpos($pathInfo, '/_profiler/') === 0 ||
            strpos($pathInfo, '/_wdt/') === 0) {
            return;
        }
        
        // Wenn der Benutzer nicht authentifiziert ist, leite zum Login um
        if (!$session->get('is_authenticated')) {
            $loginUrl = $this->urlGenerator->generate('app_login');
            $event->setResponse(new RedirectResponse($loginUrl));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }
}