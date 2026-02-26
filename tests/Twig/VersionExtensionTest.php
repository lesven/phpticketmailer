<?php

namespace App\Tests\Twig;

use App\Service\VersionService;
use App\Twig\VersionExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class VersionExtensionTest extends TestCase
{
    private VersionService $versionService;
    private VersionExtension $extension;

    protected function setUp(): void
    {
        $this->versionService = $this->createMock(VersionService::class);
        $this->extension = new VersionExtension($this->versionService);
    }

    public function testGetFunctionsReturnsThreeFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(3, $functions);
        $this->assertContainsOnlyInstancesOf(TwigFunction::class, $functions);
    }

    public function testGetFunctionsRegistersAppVersionFunction(): void
    {
        $functions = $this->extension->getFunctions();

        $names = array_map(fn(TwigFunction $f) => $f->getName(), $functions);
        $this->assertContains('app_version', $names);
    }

    public function testGetFunctionsRegistersAppUpdateTimestampFunction(): void
    {
        $functions = $this->extension->getFunctions();

        $names = array_map(fn(TwigFunction $f) => $f->getName(), $functions);
        $this->assertContains('app_update_timestamp', $names);
    }

    public function testGetFunctionsRegistersAppVersionStringFunction(): void
    {
        $functions = $this->extension->getFunctions();

        $names = array_map(fn(TwigFunction $f) => $f->getName(), $functions);
        $this->assertContains('app_version_string', $names);
    }

    public function testGetAppVersionDelegatesToVersionService(): void
    {
        $this->versionService->expects($this->once())
            ->method('getVersion')
            ->willReturn('2.5.0');

        $result = $this->extension->getAppVersion();

        $this->assertEquals('2.5.0', $result);
    }

    public function testGetAppVersionReturnsNullWhenNotAvailable(): void
    {
        $this->versionService->method('getVersion')->willReturn(null);

        $result = $this->extension->getAppVersion();

        $this->assertNull($result);
    }

    public function testGetAppUpdateTimestampDelegatesToVersionService(): void
    {
        $this->versionService->expects($this->once())
            ->method('getUpdateTimestamp')
            ->willReturn('2024-06-15 10:30:00');

        $result = $this->extension->getAppUpdateTimestamp();

        $this->assertEquals('2024-06-15 10:30:00', $result);
    }

    public function testGetAppUpdateTimestampReturnsNullWhenNotAvailable(): void
    {
        $this->versionService->method('getUpdateTimestamp')->willReturn(null);

        $result = $this->extension->getAppUpdateTimestamp();

        $this->assertNull($result);
    }

    public function testGetAppVersionStringDelegatesToVersionService(): void
    {
        $this->versionService->expects($this->once())
            ->method('getFormattedVersionString')
            ->willReturn('Version 2.5.0 (Stand: 2024-06-15 10:30:00)');

        $result = $this->extension->getAppVersionString();

        $this->assertEquals('Version 2.5.0 (Stand: 2024-06-15 10:30:00)', $result);
    }

    public function testGetAppVersionStringReturnsNotAvailableWhenNoInfo(): void
    {
        $this->versionService->method('getFormattedVersionString')
            ->willReturn('Version nicht verfügbar');

        $result = $this->extension->getAppVersionString();

        $this->assertEquals('Version nicht verfügbar', $result);
    }
}
