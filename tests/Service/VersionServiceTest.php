<?php

namespace App\Tests\Service;

use App\Service\VersionService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test-Klasse für den VersionService
 * 
 * Diese Klasse testet die Funktionalität des VersionService,
 * der für das Verwalten und Anzeigen von Versionsinformationen zuständig ist.
 */
class VersionServiceTest extends TestCase
{
    private ParameterBagInterface $parameterBag;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/version_service_test_' . uniqid();
        mkdir($this->tempDir);
        
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->parameterBag->method('get')
            ->with('kernel.project_dir')
            ->willReturn($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir . '/VERSION')) {
            unlink($this->tempDir . '/VERSION');
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    /**
     * Testet das Laden von Versionsinformationen aus einer existierenden VERSION-Datei
     * - Überprüft, dass Version und Zeitstempel korrekt aus der Datei gelesen werden
     * - Testet das Parsen des Pipe-getrennten Formats (Version|Zeitstempel)
     */
    public function testConstructorLoadsVersionInfoWhenFileExists(): void
    {
        file_put_contents($this->tempDir . '/VERSION', '2.4.3|2024-01-15 10:30:00');

        $versionService = new VersionService($this->parameterBag);

        $this->assertEquals('2.4.3', $versionService->getVersion());
        $this->assertEquals('2024-01-15 10:30:00', $versionService->getUpdateTimestamp());
    }

    /**
     * Testet das Verhalten bei fehlender VERSION-Datei
     * - Überprüft, dass null-Werte zurückgegeben werden, wenn keine Datei existiert
     * - Testet die Robustheit bei fehlenden Konfigurationsdateien
     */
    public function testConstructorHandlesMissingVersionFile(): void
    {
        $versionService = new VersionService($this->parameterBag);

        $this->assertNull($versionService->getVersion());
        $this->assertNull($versionService->getUpdateTimestamp());
    }

    /**
     * Testet die formatierte Ausgabe der Versionsinformation mit Version und Zeitstempel
     * - Überprüft das korrekte Format: "Version X.Y.Z (Stand: YYYY-MM-DD HH:MM:SS)"
     * - Testet die vollständige Anzeige bei verfügbaren Informationen
     */
    public function testGetFormattedVersionStringWithBothVersionAndTimestamp(): void
    {
        file_put_contents($this->tempDir . '/VERSION', '2.4.3|2024-01-15 10:30:00');

        $versionService = new VersionService($this->parameterBag);

        $result = $versionService->getFormattedVersionString();

        $this->assertEquals('Version 2.4.3 (Stand: 2024-01-15 10:30:00)', $result);
    }

    /**
     * Testet die formatierte Ausgabe nur mit Versionsnummer (ohne Zeitstempel)
     * - Überprüft das verkürzte Format: "Version X.Y.Z"
     * - Testet die Anzeige bei teilweise verfügbaren Informationen
     */
    public function testGetFormattedVersionStringWithOnlyVersion(): void
    {
        file_put_contents($this->tempDir . '/VERSION', '2.4.3|');

        $versionService = new VersionService($this->parameterBag);

        $result = $versionService->getFormattedVersionString();

        $this->assertEquals('Version 2.4.3', $result);
    }

    /**
     * Testet die formatierte Ausgabe bei fehlenden Versionsinformationen
     * - Überprüft die Fallback-Meldung: "Version nicht verfügbar"
     * - Testet das Verhalten bei komplett fehlenden Informationen
     */
    public function testGetFormattedVersionStringWithoutVersionInfo(): void
    {
        $versionService = new VersionService($this->parameterBag);

        $result = $versionService->getFormattedVersionString();

        $this->assertEquals('Version nicht verfügbar', $result);
    }

    /**
     * Testet das Aktualisieren der Versionsinformationen
     * - Überprüft das Schreiben neuer Version und automatischer Zeitstempel-Generierung
     * - Testet die Persistierung in der VERSION-Datei
     * - Überprüft die sofortige Verfügbarkeit der neuen Informationen
     */
    public function testUpdateVersionInfoWithNewVersion(): void
    {
        $versionService = new VersionService($this->parameterBag);

        $result = $versionService->updateVersionInfo('3.0.0', true);

        $this->assertTrue($result);
        $this->assertEquals('3.0.0', $versionService->getVersion());
        $this->assertNotNull($versionService->getUpdateTimestamp());
        
        // Verify file was written
        $this->assertTrue(file_exists($this->tempDir . '/VERSION'));
        $fileContent = file_get_contents($this->tempDir . '/VERSION');
        $this->assertStringStartsWith('3.0.0|', $fileContent);
    }

    /**
     * Testet die getVersion-Methode mit einer bestehenden Version
     * - Überprüft die korrekte Rückgabe der aktuellen Versionsnummer
     * - Testet die grundlegende Getter-Funktionalität
     */
    public function testGetVersionReturnsCurrentVersion(): void
    {
        file_put_contents($this->tempDir . '/VERSION', '1.2.3|2024-01-01 00:00:00');

        $versionService = new VersionService($this->parameterBag);

        $this->assertEquals('1.2.3', $versionService->getVersion());
    }

    /**
     * Testet die korrekte Initialisierung mit ParameterBag
     * - Überprüft, dass der Service korrekt mit Dependency Injection erstellt wird
     * - Testet die grundlegende Konstruktor-Funktionalität
     */
    public function testConstructorAcceptsParameterBag(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn('/tmp');
        
        $versionService = new VersionService($parameterBag);

        $this->assertInstanceOf(VersionService::class, $versionService);
    }
}