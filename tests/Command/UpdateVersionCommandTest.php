<?php

namespace App\Tests\Command;

use App\Command\UpdateVersionCommand;
use App\Service\VersionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateVersionCommandTest extends TestCase
{
    private VersionService $versionService;
    private UpdateVersionCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->versionService = $this->createMock(VersionService::class);
        $this->command = new UpdateVersionCommand($this->versionService);

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandName(): void
    {
        $this->assertSame('app:update-version', $this->command->getName());
    }

    public function testCommandHasNewVersionOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('new-version'));
    }

    public function testCommandHasNoTimestampOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('no-timestamp'));
    }

    public function testExecuteReturnsSuccessWhenServiceSucceeds(): void
    {
        $this->versionService->method('updateVersionInfo')->willReturn(true);
        $this->versionService->method('getVersion')->willReturn('1.2.3');
        $this->versionService->method('getUpdateTimestamp')->willReturn('2024-01-15 12:00:00');

        $statusCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $statusCode);
    }

    public function testExecuteReturnsFailureWhenServiceFails(): void
    {
        $this->versionService->method('updateVersionInfo')->willReturn(false);

        $statusCode = $this->commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $statusCode);
    }

    public function testExecuteOutputsSuccessMessage(): void
    {
        $this->versionService->method('updateVersionInfo')->willReturn(true);
        $this->versionService->method('getVersion')->willReturn('2.0.0');
        $this->versionService->method('getUpdateTimestamp')->willReturn('2024-06-01 08:00:00');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('erfolgreich aktualisiert', $output);
    }

    public function testExecuteOutputsErrorMessageWhenServiceFails(): void
    {
        $this->versionService->method('updateVersionInfo')->willReturn(false);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Fehler beim Aktualisieren', $output);
    }

    public function testExecutePassesNewVersionToService(): void
    {
        $this->versionService
            ->expects($this->once())
            ->method('updateVersionInfo')
            ->with('3.0.0', true)
            ->willReturn(true);
        $this->versionService->method('getVersion')->willReturn('3.0.0');
        $this->versionService->method('getUpdateTimestamp')->willReturn('2024-12-01 00:00:00');

        $this->commandTester->execute(['--new-version' => '3.0.0']);
    }

    public function testExecuteWithNoTimestampOption(): void
    {
        $this->versionService
            ->expects($this->once())
            ->method('updateVersionInfo')
            ->with(null, false)
            ->willReturn(true);
        $this->versionService->method('getVersion')->willReturn('1.0.0');
        $this->versionService->method('getUpdateTimestamp')->willReturn(null);

        $this->commandTester->execute(['--no-timestamp' => true]);
    }

    public function testExecuteDisplaysVersionTable(): void
    {
        $this->versionService->method('updateVersionInfo')->willReturn(true);
        $this->versionService->method('getVersion')->willReturn('1.5.0');
        $this->versionService->method('getUpdateTimestamp')->willReturn('2024-03-20 10:30:00');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('1.5.0', $output);
        $this->assertStringContainsString('2024-03-20 10:30:00', $output);
    }
}
