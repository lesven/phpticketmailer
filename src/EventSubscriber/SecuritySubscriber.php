<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SecuritySubscriber implements EventSubscriberInterface
{
    /**
     * URL Generator zum Erzeugen von internen Routen-URLs (z.B. Login-Route).
     *
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        // UrlGenerator wird per DI übergeben
        $this->urlGenerator = $urlGenerator;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            // Nur für die Hauptanfrage handeln, Subrequests ignorieren
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        $route = $request->attributes->get('_route');
        $pathInfo = $request->getPathInfo();
        // Öffentliche Routen und Entwickler-Tools explizit erlauben.
        // Hier werden die in der Anwendung benötigten Profiling-/Debug-Routen
        // sowie statische Assets (bundles) ausgeschlossen, damit diese
        // auch ohne Authentifizierung erreichbar bleiben.
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
            // Pfade abgleichen, z.B. /bundles/... oder /_profiler/...
            strpos($pathInfo, '/bundles/') === 0 ||
            strpos($pathInfo, '/_profiler/') === 0 ||
            strpos($pathInfo, '/_wdt/') === 0) {
            // Keine Aktion erforderlich für diese Routen
            return;
        }

        // Session-Flag prüfen: falls nicht authentifiziert, auf Login umleiten.
        // Erwartet: Ein einfacher Session-Flag 'is_authenticated' wird gesetzt,
        // sobald sich ein Benutzer erfolgreich anmeldet. Dies ist bewusst
        // einfach gehalten (kein full Symfony Security), weil die App eine
        // sehr begrenzte Authentifizierung verwendet.
        if (!$session->get('is_authenticated')) {
            $loginUrl = $this->urlGenerator->generate('app_login');
            // RedirectResponse setzt die Antwort und verhindert weitere Verarbeitung
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