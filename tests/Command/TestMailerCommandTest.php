<?php

namespace App\Tests\Command;

use App\Command\TestMailerCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;

class TestMailerCommandTest extends TestCase
{
    private MailerInterface $mailer;
    private TestMailerCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->command = new TestMailerCommand($this->mailer);

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandName(): void
    {
        $this->assertSame('app:test-mailer', $this->command->getName());
    }

    public function testExecuteSuccessfullySendsEmail(): void
    {
        $this->mailer->expects($this->once())->method('send');

        $statusCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $statusCode);
    }

    public function testExecuteOutputsSuccessMessage(): void
    {
        $this->mailer->method('send');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Test-E-Mail wurde erfolgreich gesendet', $output);
    }

    public function testExecuteReturnsFailureOnException(): void
    {
        $this->mailer
            ->method('send')
            ->willThrowException(new \Exception('Connection refused'));

        $statusCode = $this->commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $statusCode);
    }

    public function testExecuteOutputsErrorMessageOnException(): void
    {
        $this->mailer
            ->method('send')
            ->willThrowException(new \Exception('SMTP timeout'));

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Fehler beim Senden der E-Mail', $output);
        $this->assertStringContainsString('SMTP timeout', $output);
    }
}
