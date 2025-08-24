<?php

namespace App\Command;

use App\Event\User\UserImportStartedEvent;
use App\Event\User\UserImportCompletedEvent;
use App\Event\Email\EmailSentEvent;
use App\ValueObject\TicketId;
use App\ValueObject\Username;
use App\ValueObject\EmailAddress;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:test-events',
    description: 'Test Domain Events - dispatcht Events und zeigt Logging',
)]
class TestEventsCommand extends Command
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔥 Testing Domain Events');

        // Test 1: User Import Events
        $io->section('1. User Import Events');
        
        $io->text('📤 Dispatching UserImportStartedEvent...');
        $this->eventDispatcher->dispatch(new UserImportStartedEvent(100, 'test-users.csv', false));
        
        $io->text('📤 Dispatching UserImportCompletedEvent...');
        $this->eventDispatcher->dispatch(new UserImportCompletedEvent(
            95, // success
            5,  // errors
            ['User alice validation failed', 'User bob email invalid'],
            'test-users.csv',
            2.5 // duration
        ));

        // Test 2: Email Events
        $io->section('2. Email Events');
        
        $io->text('📤 Dispatching EmailSentEvent...');
        $this->eventDispatcher->dispatch(new EmailSentEvent(
            TicketId::fromString('T-12345'),
            Username::fromString('test_user'),
            EmailAddress::fromString('test@example.com'),
            'Your Ticket Update',
            true, // test mode
            'System Issue Fix'
        ));

        // Test 3: Direct Logger Test
        $io->section('3. Direct Logger Test');
        
        $io->text('📝 Writing direct log entries...');
        $this->logger->info('Direct log test - Domain Events are working!', [
            'test' => true,
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        ]);
        
        $this->logger->warning('Test warning message', ['level' => 'warning']);
        $this->logger->error('Test error message', ['level' => 'error']);

        $io->success('✅ Events dispatched successfully!');
        
        $io->note([
            'Check the logs to see if events were processed:',
            '',
            'var/log/dev.log     - Symfony logs (if configured)',
            'PHP error_log       - System default error log',
            '',
            'To monitor logs in real-time:',
            'tail -f var/log/dev.log',
            'Or check your system error log location.'
        ]);

        return Command::SUCCESS;
    }
}