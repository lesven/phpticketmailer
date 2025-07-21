<?php
/**
 * DashboardControllerTest.php
 *
 * Test-Klasse für den DashboardController zur Überprüfung der Statistik-Anzeige
 *
 * @package App\Tests\Controller
 */

namespace App\Tests\Repository;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test-Klasse für DashboardController
 */
class DashboardControllerTest extends KernelTestCase
{
    /**
     * Test für die Dashboard-Statistiken
     */
    public function testDashboardStatistics(): void
    {
        $client = static::createClient();
        
        // Besuche das Dashboard
        $crawler = $client->request('GET', '/');
        
        // Überprüfe, dass die Seite geladen wurde
        self::assertResponseIsSuccessful();
        
        // Überprüfe, dass der Statistik-Bereich vorhanden ist
        self::assertSelectorTextContains('h2', 'E-Mail-Statistiken');
        
        // Überprüfe, dass alle Statistik-Karten vorhanden sind
        self::assertSelectorExists('.card .card-title:contains("Gesamt E-Mails")');
        self::assertSelectorExists('.card .card-title:contains("Erfolgreich")');
        self::assertSelectorExists('.card .card-title:contains("Fehlgeschlagen")');
        self::assertSelectorExists('.card .card-title:contains("Übersprungen")');
        self::assertSelectorExists('.card .card-title:contains("Einzigartige Benutzer")');
        self::assertSelectorExists('.card .card-title:contains("Erfolgsrate")');
    }

    /**
     * Test für die API-Route der Statistiken
     */
    public function testStatisticsApiEndpoint(): void
    {
        $client = static::createClient();
        
        // Rufe die API-Route auf
        $client->request('GET', '/api/statistics');
        
        // Überprüfe, dass die Antwort erfolgreich ist
        self::assertResponseIsSuccessful();
        
        // Überprüfe, dass es sich um JSON handelt
        self::assertResponseHeaderSame('content-type', 'application/json');
        
        // Hole die Antwort und dekodiere sie
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);
        
        // Überprüfe, dass alle erforderlichen Schlüssel vorhanden sind
        self::assertArrayHasKey('total', $content);
        self::assertArrayHasKey('successful', $content);
        self::assertArrayHasKey('failed', $content);
        self::assertArrayHasKey('skipped', $content);
        self::assertArrayHasKey('unique_recipients', $content);
        self::assertArrayHasKey('success_rate', $content);
        
        // Überprüfe, dass die Werte numerisch sind
        self::assertTrue(is_int($content['total']));
        self::assertTrue(is_int($content['successful']));
        self::assertTrue(is_int($content['failed']));
        self::assertTrue(is_int($content['skipped']));
        self::assertTrue(is_int($content['unique_recipients']));
        self::assertTrue(is_float($content['success_rate']) || is_int($content['success_rate']));
    }
}
