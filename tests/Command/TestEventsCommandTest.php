<?php

namespace App\Tests\Command;

use App\Command\TestEventsCommand;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TestEventsCommandTest extends TestCase
{
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private TestEventsCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->command = new TestEventsCommand($this->eventDispatcher, $this->logger);

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandName(): void
    {
        $this->assertSame('app:test-events', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertStringContainsString('Domain Events', $this->command->getDescription());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $statusCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $statusCode);
    }

    public function testExecuteDispatchesThreeEvents(): void
    {
        $this->eventDispatcher
            ->expects($this->exactly(3))
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->commandTester->execute([]);
    }

    public function testExecuteCallsLogger(): void
    {
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $this->commandTester->execute([]);
    }

    public function testExecuteCallsLoggerWithWarningAndError(): void
    {
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $this->logger->method('info');
        $this->logger->expects($this->once())->method('warning');
        $this->logger->expects($this->once())->method('error');

        $this->commandTester->execute([]);
    }

    public function testOutputContainsSuccessMessage(): void
    {
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Events dispatched successfully', $output);
    }
}
