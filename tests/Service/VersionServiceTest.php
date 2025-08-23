<?php

namespace App\Tests\Service;

use App\Service\VersionService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use PHPUnit\Framework\TestCase;

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

    public function testConstructorLoadsVersionInfoWhenFileExists(): void
    {
        file_put_contents($this->tempDir . '/VERSION', '2.4.3|2024-01-15 10:30:00');

        $versionService = new VersionService($this->parameterBag);

        $this->assertEquals('2.4.3', $versionService->getVersion());
        $this->assertEquals('2024-01-15 10:30:00', $versionService->getUpdateTimestamp());
    }

    public function testConstructorHandlesMissingVersionFile(): void
    {
        $versionService = new VersionService($this->parameterBag);

        $this->assertNull($versionService->getVersion());
        $this->assertNull($versionService->getUpdateTimestamp());
    }

    public function testGetFormattedVersionStringWithBothVersionAndTimestamp(): void
    {
        file_put_contents($this->tempDir . '/VERSION', '2.4.3|2024-01-15 10:30:00');

        $versionService = new VersionService($this->parameterBag);

        $result = $versionService->getFormattedVersionString();

        $this->assertEquals('Version 2.4.3 (Stand: 2024-01-15 10:30:00)', $result);
    }

    public function testGetFormattedVersionStringWithOnlyVersion(): void
    {
        file_put_contents($this->tempDir . '/VERSION', '2.4.3|');

        $versionService = new VersionService($this->parameterBag);

        $result = $versionService->getFormattedVersionString();

        $this->assertEquals('Version 2.4.3', $result);
    }

    public function testGetFormattedVersionStringWithoutVersionInfo(): void
    {
        $versionService = new VersionService($this->parameterBag);

        $result = $versionService->getFormattedVersionString();

        $this->assertEquals('Version nicht verfügbar', $result);
    }

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

    public function testGetVersionReturnsCurrentVersion(): void
    {
        file_put_contents($this->tempDir . '/VERSION', '1.2.3|2024-01-01 00:00:00');

        $versionService = new VersionService($this->parameterBag);

        $this->assertEquals('1.2.3', $versionService->getVersion());
    }

    public function testConstructorAcceptsParameterBag(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn('/tmp');
        
        $versionService = new VersionService($parameterBag);

        $this->assertInstanceOf(VersionService::class, $versionService);
    }
}