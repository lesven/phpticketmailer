<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class DashboardTemplateTest extends TestCase
{
    public function testDashboardTemplateShowsOneRowPerMonthWithConcatenatedDomains(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new Environment($loader);
        // Minimal replacement for Symfony-specific functions used in templates
        $twig->addFunction(new \Twig\TwigFunction('path', function ($name, $params = []) { return '#'; }));
        $twig->addFunction(new \Twig\TwigFunction('asset', function ($path) { return $path; }));
        $twig->addFunction(new \Twig\TwigFunction('url', function ($name, $params = []) { return '#'; }));
        // Template helper used in footer
        $twig->addFunction(new \Twig\TwigFunction('app_version_string', function () { return 'v0.0.0-test'; }));
        
        // Minimal 'app' global to satisfy template checks (session, request, flashes)
        $appGlobal = new class {
            public function session() {}
        };
        // Provide minimal globals expected by base.html.twig
        $twig->addGlobal('app', new \ArrayObject([
            'session' => new class {
                public function get($key) { return false; }
            },
            'request' => new class {
                public function get($k) { return null; }
            },
            'flashes' => []
        ]));

        $monthlyDomainStatistics = [
            ['month' => '2026-01', 'domains' => ['company-a.com' => 2, 'company-b.com' => 1], 'total_users' => 3],
            ['month' => '2026-02', 'domains' => [], 'total_users' => 0],
        ];

        $rendered = $twig->render('dashboard/index.html.twig', [
            'monthlyDomainStatistics' => $monthlyDomainStatistics,
            // required by template but not relevant for this test
            'monthlyStatistics' => [],
            'recentEmails' => [],
            'statistics' => [],
        ]);

        // Monat 2026-01 sollte genau einmal vorkommen (eine Zeile pro Monat)
        $this->assertSame(1, substr_count($rendered, '2026-01'));

        // Domains sollten als "domain: count" dargestellt werden
        $this->assertStringContainsString('company-a.com: 2', $rendered);
        $this->assertStringContainsString('company-b.com: 1', $rendered);

        // Monat ohne Daten sollte den Hinweis 'Keine Daten' enthalten
        $this->assertStringContainsString('Keine Daten', $rendered);
    }
}
