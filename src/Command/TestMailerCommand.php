<?php
declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

#[AsCommand(
    name: 'app:test-mailer',
    description: 'Sendet eine Test-E-Mail, um die Mailer-Konfiguration zu prüfen',
)]
final class TestMailerCommand extends Command
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    protected function configure(): void
    {
        $this->setHelp('Dieser Befehl sendet eine einfache Test-E-Mail an test@example.com, um die Mailer-Konfiguration zu überprüfen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $email = (new Email())
                ->from(new Address('noreply@example.com', 'Test Sender'))
                ->to('sven.heising@gmail.com')
                ->subject('Einfache Test-E-Mail - ' . date('Y-m-d H:i:s'))
                ->text('Dies ist eine Test-E-Mail, um zu prüfen, ob E-Mails korrekt gesendet werden und in Mailpit ankommen.')
                ->html('<p>Dies ist eine Test-E-Mail, um zu prüfen, ob E-Mails korrekt gesendet werden und in Mailpit ankommen.</p>');
            
            $this->mailer->send($email);
            
            $io->success('Test-E-Mail wurde erfolgreich gesendet! Überprüfen Sie Mailpit unter http://localhost:64174');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Fehler beim Senden der E-Mail: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}
